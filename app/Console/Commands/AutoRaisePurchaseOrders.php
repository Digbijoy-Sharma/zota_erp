<?php

namespace App\Console\Commands;

use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\PurchaseLine;
use App\Transaction;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use App\Utils\Util;
use App\Variation;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Combines each store's OPEN auto-requisitions (maintained in real time
 * by AutoRequisitionUtil) into a SINGLE Purchase Order to that store's
 * assigned supplier, honoring the per-store auto-PO frequency.
 *
 * Scheduled daily; the per-store frequency (1-30 days, resolved via
 * Business::effectiveAutoPoFrequencyDays()) is enforced here by comparing
 * days since the store's last auto-PO (business.common_settings
 * ['last_auto_po_at']). A store is processed only when it is "due" AND has
 * open requisition lines, so an empty cycle does not consume the timer.
 *
 * Reuses the existing PO plumbing exactly:
 *   - purchase_lines.purchase_requisition_line_id back-links each PO line
 *     to its requisition line;
 *   - transactions.purchase_requisition_ids records the consumed reqs;
 *   - ProductUtil::updatePurchaseOrderLine bumps po_quantity_purchased;
 *   - TransactionUtil::updatePurchaseOrderStatus flips consumed reqs to
 *     partial/completed (idempotent — a consumed line is never re-ordered).
 */
class AutoRaisePurchaseOrders extends Command
{
    protected $signature = 'pos:autoRaisePurchaseOrders
        {--business_id= : Process only this business}
        {--force : Ignore the per-store frequency gate (still requires open requisitions)}';

    protected $description = 'Raise combined auto purchase orders from open store requisitions to each store\'s assigned supplier.';

    protected $productUtil;

    protected $transactionUtil;

    protected $commonUtil;

    public function __construct(ProductUtil $productUtil, TransactionUtil $transactionUtil, Util $commonUtil)
    {
        parent::__construct();
        $this->productUtil = $productUtil;
        $this->transactionUtil = $transactionUtil;
        $this->commonUtil = $commonUtil;
    }

    public function handle()
    {
        $today = Carbon::now();

        $query = Business::where('is_active', 1);
        if ($this->option('business_id')) {
            $query->where('id', $this->option('business_id'));
        }

        $processed = 0;
        $raised = 0;

        $query->chunkById(50, function ($businesses) use ($today, &$processed, &$raised) {
            foreach ($businesses as $business) {
                $processed++;
                try {
                    if ($this->raiseForBusiness($business, $today)) {
                        $raised++;
                    }
                } catch (\Throwable $e) {
                    Log::error('AutoRaisePurchaseOrders failed for business '.$business->id.': '.$e->getMessage());
                }
            }
        });

        $this->info("Auto-PO: processed {$processed} business(es); raised PO(s) for {$raised}.");

        return 0;
    }

    /**
     * Returns true if at least one PO was raised for this business.
     */
    protected function raiseForBusiness(Business $business, Carbon $today)
    {
        // 1. Feature enabled for this store (own override or chain default)?
        $frequency = $business->effectiveAutoPoFrequencyDays();
        if (empty($frequency)) {
            return false;
        }

        // 2. Is the store "due"? (skip the gate under --force)
        //    Compare CALENDAR days (startOfDay) so a frequency of 1 fires
        //    every day regardless of the run's clock time — a datetime diff
        //    would floor 23h59m to 0 and intermittently skip a cycle. Guard
        //    against a future last_auto_po_at (clock skew / DB restore) by
        //    treating "in the future" as not-due.
        if (! $this->option('force')) {
            $last = is_array($business->common_settings) ? ($business->common_settings['last_auto_po_at'] ?? null) : null;
            if (! empty($last)) {
                $last_day = Carbon::parse($last)->startOfDay();
                $today_day = $today->copy()->startOfDay();
                if ($last_day->greaterThan($today_day)) {
                    return false;
                }
                if ($last_day->diffInDays($today_day) < $frequency) {
                    return false;
                }
            }
        }

        // 3. Target supplier = the supplier the superadmin assigned to this
        //    store. Assignment clones the master supplier into the store with
        //    contacts.common_supplier_id set, so the store's assigned supplier
        //    is that clone. Use it directly (no separate designation). If more
        //    than one is assigned, use the earliest and log which was chosen.
        $assigned_suppliers = Contact::where('business_id', $business->id)
            ->whereIn('type', ['supplier', 'both'])
            ->whereNotNull('common_supplier_id')
            ->orderBy('id')
            ->get();

        if ($assigned_suppliers->isEmpty()) {
            Log::warning("Auto-PO skipped for business {$business->id}: no supplier assigned to the store.");

            return false;
        }
        if ($assigned_suppliers->count() > 1) {
            Log::info("Auto-PO business {$business->id}: {$assigned_suppliers->count()} suppliers assigned; using earliest (#{$assigned_suppliers->first()->id}).");
        }
        $supplier_clone = $assigned_suppliers->first();

        // 4. One PO per location that has open requisition lines.
        $any_raised = false;
        $any_failed = false;
        $location_ids = BusinessLocation::where('business_id', $business->id)->pluck('id');
        foreach ($location_ids as $location_id) {
            $result = $this->raiseForLocation($business, $location_id, $supplier_clone, $today);
            if ($result === 'raised') {
                $any_raised = true;
            } elseif ($result === 'failed') {
                $any_failed = true;
            }
        }

        // 5. Stamp the timer ONLY when at least one PO was raised AND no
        //    location failed. If any location errored, leave the timer so
        //    the next daily run retries it immediately instead of starving
        //    that location's real stockouts for a full frequency period.
        if ($any_raised && ! $any_failed) {
            $cs = is_array($business->common_settings) ? $business->common_settings : [];
            $cs['last_auto_po_at'] = $today->toDateTimeString();
            $business->common_settings = $cs;
            $business->save();
        }

        return $any_raised;
    }

    /**
     * Build a single PO for one location from its open auto-requisitions.
     *
     * @return string  'raised' (a PO was created), 'empty' (nothing to do),
     *                 or 'failed' (an error rolled the attempt back).
     */
    protected function raiseForLocation(Business $business, $location_id, Contact $supplier_clone, Carbon $today)
    {
        // Cheap pre-check outside the transaction to avoid locking when
        // there's nothing to do.
        $has_open = Transaction::where('business_id', $business->id)
            ->where('location_id', $location_id)
            ->where('type', 'purchase_requisition')
            ->where('is_auto_generated', 1)
            ->where('status', '!=', 'completed')
            ->exists();
        if (! $has_open) {
            return 'empty';
        }

        DB::beginTransaction();
        try {
            // Lock the open headers + their lines FOR UPDATE so a concurrent
            // run (e.g. the scheduled run overlapping a manual --force run)
            // blocks here and then sees po_quantity_purchased already bumped
            // -> remaining 0 -> nothing to re-order. Prevents double-ordering.
            $req_ids = Transaction::where('business_id', $business->id)
                ->where('location_id', $location_id)
                ->where('type', 'purchase_requisition')
                ->where('is_auto_generated', 1)
                ->where('status', '!=', 'completed')
                ->lockForUpdate()
                ->pluck('id')
                ->all();

            if (empty($req_ids)) {
                DB::commit();

                return 'empty';
            }

            $req_lines = PurchaseLine::whereIn('transaction_id', $req_ids)
                ->lockForUpdate()
                ->get()
                ->filter(function ($l) {
                    return ((float) $l->quantity - (float) ($l->po_quantity_purchased ?? 0)) > 0;
                })
                ->values();

            if ($req_lines->isEmpty()) {
                DB::commit();

                return 'empty';
            }

            $variations = Variation::whereIn('id', $req_lines->pluck('variation_id')->unique()->all())
                ->get()
                ->keyBy('id');

            // Consolidate to ONE line per variation (defensive against any
            // duplicate open lines): the need is a single max-current value,
            // never the sum. We order it once and consume every contributing
            // requisition line so none is left to re-order next cycle.
            $by_variation = $req_lines->groupBy('variation_id');

            $po = null;
            $total_before_tax = 0;
            $final_total = 0;
            $skipped = 0;
            $line_count = 0;

            foreach ($by_variation as $variation_id => $lines) {
                $primary = $lines->first();
                $remaining = (float) $primary->quantity - (float) ($primary->po_quantity_purchased ?? 0);
                if ($remaining <= 0) {
                    continue;
                }

                $var = $variations[$variation_id] ?? null;
                $pp_exc = $var ? (float) $var->default_purchase_price : 0;
                $pp_inc = $var ? (float) $var->dpp_inc_tax : 0;
                if ($pp_inc <= 0) {
                    $pp_inc = $pp_exc;
                }

                // Never auto-order a deleted variation or a product with no
                // purchase price (would create a 0-priced/ dangling line and
                // silently consume the need). Skip it — it stays in the
                // requisition and is retried once priced.
                if (empty($var) || $pp_inc <= 0) {
                    $skipped++;
                    Log::warning("Auto-PO business {$business->id} location {$location_id}: skipped variation {$variation_id} (missing variation or no purchase price).");
                    continue;
                }

                // Create the PO header lazily, only once we have a real line.
                if (is_null($po)) {
                    $ref_count = $this->commonUtil->setAndGetReferenceCount('purchase_order', $business->id);
                    $ref_no = $this->commonUtil->generateReferenceNumber('purchase_order', $ref_count, $business->id);

                    $po = Transaction::create([
                        'business_id' => $business->id,
                        'location_id' => $location_id,
                        'type' => 'purchase_order',
                        'status' => 'ordered',
                        'payment_status' => 'due',
                        'is_auto_generated' => 1,
                        'contact_id' => $supplier_clone->id,
                        'created_by' => $business->owner_id ?: auth()->id(),
                        'transaction_date' => $today->toDateTimeString(),
                        'ref_no' => $ref_no,
                        'purchase_requisition_ids' => $req_ids,
                        'exchange_rate' => 1,
                        'discount_type' => 'fixed',
                        'discount_amount' => 0,
                        'tax_amount' => 0,
                        'total_before_tax' => 0,
                        'final_total' => 0,
                    ]);
                }

                $item_tax = max(0, $pp_inc - $pp_exc);
                $total_before_tax += $remaining * $pp_exc;
                $final_total += $remaining * $pp_inc;
                $line_count++;

                $po->purchase_lines()->create([
                    'product_id' => $primary->product_id,
                    'variation_id' => $variation_id,
                    'quantity' => $remaining,
                    'pp_without_discount' => $pp_exc,
                    'discount_percent' => 0,
                    'purchase_price' => $pp_exc,
                    'purchase_price_inc_tax' => $pp_inc,
                    'item_tax' => $item_tax * $remaining,
                    'purchase_requisition_line_id' => $primary->id,
                ]);

                // Consume EVERY requisition line for this variation.
                foreach ($lines as $l) {
                    $rem_l = (float) $l->quantity - (float) ($l->po_quantity_purchased ?? 0);
                    if ($rem_l > 0) {
                        $this->productUtil->updatePurchaseOrderLine($l->id, $rem_l, 0);
                    }
                }
            }

            // Every line was skipped (no priceable products) -> no PO.
            if (is_null($po)) {
                DB::commit();

                return 'empty';
            }

            $po->total_before_tax = $total_before_tax;
            // Keep the header invariant final_total = total_before_tax + tax.
            $po->tax_amount = max(0, $final_total - $total_before_tax);
            $po->final_total = $final_total;
            $po->save();

            // Recompute requisition statuses -> completed/partial.
            $this->transactionUtil->updatePurchaseOrderStatus($req_ids);

            DB::commit();

            $this->line("  Business {$business->id} / location {$location_id}: PO {$po->ref_no} raised with {$line_count} line(s) to supplier #{$supplier_clone->id}.".($skipped ? " ({$skipped} skipped, unpriced)" : ''));

            return 'raised';
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Auto-PO for business {$business->id} location {$location_id} failed: ".$e->getMessage());

            return 'failed';
        }
    }
}
