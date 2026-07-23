<?php

namespace App\Utils;

use App\Business;
use App\PurchaseLine;
use App\Transaction;
use App\VariationLocationDetails;
use Illuminate\Support\Facades\Log;

/**
 * Maintains each store's OPEN "auto purchase requisition" in real time.
 *
 * Called from the two stock-mutation chokepoints in ProductUtil after
 * qty_available changes, for the single (product, variation, location)
 * that just moved:
 *
 *   - When stock falls to/below the per-store MIN (min_quantity), ensure
 *     the store has an open auto-requisition line for that product with
 *     quantity = max(0, max_quantity - qty_available). The value is kept
 *     LIVE because this runs on every stock change (e.g. 12-5=7, then a
 *     later sale to stock 2 updates it to 12-2=10).
 *
 *   - When stock rises back above MIN before any PO has consumed the
 *     line, the not-yet-ordered line is dropped (and an emptied header
 *     removed) so stale requisitions don't linger.
 *
 * There is exactly ONE open auto-requisition header per (business,
 * location); all below-min products for that store share it. This class
 * NEVER throws — a failure here must never break a sale or GRN.
 *
 * Extends Util for setAndGetReferenceCount()/generateReferenceNumber().
 */
class AutoRequisitionUtil extends Util
{
    /**
     * Sync the store's open auto-requisition for one variation/location.
     *
     * @param  int  $business_id
     * @param  int  $location_id
     * @param  int  $product_id
     * @param  int  $variation_id
     * @param  VariationLocationDetails|null  $vld  Already-loaded VLD row
     *         (avoids a re-read on the hot path); fetched if null.
     */
    public function syncForVariationLocation($business_id, $location_id, $product_id, $variation_id, VariationLocationDetails $vld = null)
    {
        try {
            if (empty($business_id) || empty($location_id) || empty($variation_id)) {
                return;
            }

            // Feature gate FIRST (memoized per request). Stores without
            // auto-PO enabled — and bulk stock operations on them (opening
            // stock, GRN, transfers of hundreds of lines) — short-circuit
            // here with at most one cached frequency lookup and issue ZERO
            // requisition queries.
            if (! $this->autoPoEnabled($business_id)) {
                return;
            }

            if (is_null($vld)) {
                $vld = VariationLocationDetails::where('variation_id', $variation_id)
                    ->where('location_id', $location_id)
                    ->first();
            }
            if (empty($vld)) {
                return;
            }

            $min = (float) $vld->min_quantity;
            $qty = (float) $vld->qty_available;
            $max = (float) $vld->max_quantity;

            $below_min = $min > 0 && $qty <= $min;
            $required = $max - $qty;

            if ($below_min && $required > 0) {
                $this->upsertOpenLine(
                    $business_id,
                    $location_id,
                    $product_id,
                    $variation_id,
                    $vld->product_variation_id,
                    $required
                );
            } else {
                // At/above min (or max not configured): drop the pending line.
                $this->removeOpenUnorderedLine($business_id, $location_id, $variation_id);
            }
        } catch (\Throwable $e) {
            Log::error('AutoRequisition sync failed: '.$e->getMessage(), [
                'business_id' => $business_id,
                'location_id' => $location_id,
                'product_id' => $product_id,
                'variation_id' => $variation_id,
            ]);
        }
    }

    /**
     * Whether the store has auto-PO enabled (own override or chain-wide
     * default). Memoized per process/request so repeated below-min lines
     * and bulk stock operations don't re-query the business/template.
     */
    protected function autoPoEnabled($business_id)
    {
        static $cache = [];

        if (! array_key_exists($business_id, $cache)) {
            $business = Business::find($business_id);
            $cache[$business_id] = $business ? $business->effectiveAutoPoFrequencyDays() : null;
        }

        return ! empty($cache[$business_id]);
    }

    /**
     * Ensure an open, not-yet-ordered requisition line exists for the
     * variation and carries the current required quantity.
     */
    protected function upsertOpenLine($business_id, $location_id, $product_id, $variation_id, $product_variation_id, $required)
    {
        $header = $this->getOrCreateOpenHeader($business_id, $location_id);

        // An open line = this variation, not yet pulled into any PO
        // (po_quantity_purchased = 0/null), in ANY of the location's open
        // auto-requisition headers — not just the newest. This closes a
        // race where two headers are created concurrently and the variation
        // would otherwise get a duplicate line in each (later double-ordered).
        $open_header_ids = Transaction::where('business_id', $business_id)
            ->where('location_id', $location_id)
            ->where('type', 'purchase_requisition')
            ->where('is_auto_generated', 1)
            ->where('status', '!=', 'completed')
            ->pluck('id');

        $line = PurchaseLine::whereIn('transaction_id', $open_header_ids)
            ->where('variation_id', $variation_id)
            ->where(function ($q) {
                $q->whereNull('po_quantity_purchased')->orWhere('po_quantity_purchased', 0);
            })
            ->first();

        if ($line) {
            // Keep the required quantity live as stock moves.
            if ((float) $line->quantity !== (float) $required) {
                $line->quantity = $required;
                $line->save();
            }

            return;
        }

        // Mirror the manual-requisition line shape (PurchaseRequisitionController::store).
        $header->purchase_lines()->create([
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'quantity' => $required,
            'purchase_price_inc_tax' => 0,
            'item_tax' => 0,
        ]);
    }

    /**
     * Get the store/location's single open auto-requisition header,
     * creating it (with a proper ref number) if none exists.
     */
    protected function getOrCreateOpenHeader($business_id, $location_id)
    {
        $header = Transaction::where('business_id', $business_id)
            ->where('location_id', $location_id)
            ->where('type', 'purchase_requisition')
            ->where('is_auto_generated', 1)
            ->where('status', '!=', 'completed')
            ->orderBy('id', 'desc')
            ->first();

        if ($header) {
            return $header;
        }

        $ref_count = $this->setAndGetReferenceCount('purchase_requisition', $business_id);
        $ref_no = $this->generateReferenceNumber('purchase_requisition', $ref_count, $business_id);

        return Transaction::create([
            'business_id' => $business_id,
            'location_id' => $location_id,
            'type' => 'purchase_requisition',
            'status' => 'ordered',
            'is_auto_generated' => 1,
            'created_by' => $this->autoCreatedByUserId($business_id),
            'transaction_date' => \Carbon::now()->toDateTimeString(),
            'ref_no' => $ref_no,
        ]);
    }

    /**
     * Remove the pending (not-yet-ordered) line for a variation across
     * the store's open auto-requisition headers, and delete any header
     * left empty as a result.
     */
    protected function removeOpenUnorderedLine($business_id, $location_id, $variation_id)
    {
        $header_ids = Transaction::where('business_id', $business_id)
            ->where('location_id', $location_id)
            ->where('type', 'purchase_requisition')
            ->where('is_auto_generated', 1)
            ->where('status', '!=', 'completed')
            ->pluck('id');

        if ($header_ids->isEmpty()) {
            return;
        }

        PurchaseLine::whereIn('transaction_id', $header_ids)
            ->where('variation_id', $variation_id)
            ->where(function ($q) {
                $q->whereNull('po_quantity_purchased')->orWhere('po_quantity_purchased', 0);
            })
            ->delete();

        // Drop headers that no longer have any lines.
        foreach ($header_ids as $hid) {
            $has_lines = PurchaseLine::where('transaction_id', $hid)->exists();
            if (! $has_lines) {
                Transaction::where('id', $hid)->where('is_auto_generated', 1)->delete();
            }
        }
    }

    /**
     * A valid created_by user id for auto-generated documents: the acting
     * user when in a request, else the store owner (created_by is NOT
     * nullable and is an FK to users).
     */
    protected function autoCreatedByUserId($business_id)
    {
        $uid = auth()->id();
        if (! empty($uid)) {
            return $uid;
        }

        return Business::where('id', $business_id)->value('owner_id');
    }
}
