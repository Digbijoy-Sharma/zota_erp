<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use App\InvoiceLayout;
use App\InvoiceScheme;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Centralised invoice scheme/layout management for the store chain.
 *
 * Master schemes/layouts live in the template (super admin's, lowest
 * id) business. Assigning a master pair to a store business:
 *   1. creates/refreshes a read-only mirror scheme + layout inside
 *      that business (so store-side dropdowns keep working),
 *   2. points every location of the business (each store has exactly
 *      one auto-created location) at the mirrors — both the POS and
 *      direct-sale slots,
 *   3. marks the mirrors default and REMOVES the store's other,
 *      non-mirrored schemes so no sale can ever be numbered outside
 *      the shared series (GST accuracy).
 *
 * The invoice COUNTER lives only on the master scheme; mirrors
 * delegate to it at generation time (TransactionUtil::
 * allocateInvoiceNumber), producing one gapless serial series across
 * all stores that share a GST number.
 */
class InvoiceAssignmentController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! auth()->user()->can('superadmin')) {
                abort(403, 'Unauthorized action.');
            }

            return $next($request);
        });
    }

    /**
     * The template business is the chain-wide convention: the first
     * (lowest id) business, owned by the super admin.
     */
    public static function templateBusinessId()
    {
        return Business::orderBy('id')->value('id');
    }

    public function index()
    {
        $template_business_id = self::templateBusinessId();

        $master_schemes = InvoiceScheme::where('business_id', $template_business_id)
            ->withCount('mirrors')
            ->orderBy('id')
            ->get();

        $master_layouts = InvoiceLayout::where('business_id', $template_business_id)
            ->orderBy('id')
            ->get(['id', 'name', 'is_default']);

        $businesses = Business::where('id', '!=', $template_business_id)
            ->with(['locations' => function ($q) {
                $q->select('id', 'business_id', 'invoice_scheme_id', 'invoice_layout_id');
            }])
            ->orderBy('id')
            ->get(['id', 'name', 'tax_number_1', 'is_active']);

        // Resolve each business's currently assigned scheme/layout
        // (via its first location) for display.
        $scheme_ids = [];
        $layout_ids = [];
        foreach ($businesses as $b) {
            $loc = $b->locations->first();
            if ($loc) {
                $scheme_ids[] = $loc->invoice_scheme_id;
                $layout_ids[] = $loc->invoice_layout_id;
            }
        }
        $schemes_by_id = InvoiceScheme::whereIn('id', array_filter($scheme_ids))->get()->keyBy('id');
        $layouts_by_id = InvoiceLayout::whereIn('id', array_filter($layout_ids))->get(['id', 'name', 'master_invoice_layout_id'])->keyBy('id');

        return view('superadmin::invoice_assignment.index')
            ->with(compact('master_schemes', 'master_layouts', 'businesses', 'schemes_by_id', 'layouts_by_id'));
    }

    /**
     * Set/update a business's GST number (GSTIN).
     */
    public function saveBusinessGst(Request $request)
    {
        $business = Business::findOrFail($request->input('business_id'));
        $business->tax_number_1 = trim((string) $request->input('tax_number_1')) ?: null;

        // The receipt prints "tax_label_1: tax_number_1" — make sure
        // the number carries its proper caption on invoices.
        if (! empty($business->tax_number_1) && empty($business->tax_label_1)) {
            $business->tax_label_1 = 'GSTIN';
        }

        $business->save();

        return redirect()->back()->with('status', [
            'success' => 1,
            'msg' => __('superadmin::lang.gst_updated', ['business' => $business->name]),
        ]);
    }

    /**
     * Set the GST number / state label on a master scheme — this
     * documents which state series the scheme represents and is used
     * to validate and bulk-match store assignment.
     */
    public function saveSchemeGst(Request $request)
    {
        $scheme = InvoiceScheme::where('business_id', self::templateBusinessId())
            ->findOrFail($request->input('scheme_id'));
        $scheme->gst_number = trim((string) $request->input('gst_number')) ?: null;
        $scheme->state_name = trim((string) $request->input('state_name')) ?: null;
        $scheme->save();

        return redirect()->back()->with('status', [
            'success' => 1,
            'msg' => __('superadmin::lang.scheme_gst_updated', ['scheme' => $scheme->name]),
        ]);
    }

    /**
     * Assign a master scheme + layout to businesses.
     *
     * mode 'selected'      — assign to the posted business_ids[].
     * mode 'matching_gst'  — assign to every active business whose
     *                        tax_number_1 equals the scheme's
     *                        gst_number (bulk, state-wise).
     */
    public function assign(Request $request)
    {
        $template_business_id = self::templateBusinessId();

        $master_scheme = InvoiceScheme::where('business_id', $template_business_id)
            ->findOrFail($request->input('master_scheme_id'));
        $master_layout = InvoiceLayout::where('business_id', $template_business_id)
            ->findOrFail($request->input('master_layout_id'));

        if ($request->input('mode') == 'matching_gst') {
            if (empty($master_scheme->gst_number)) {
                return redirect()->back()->with('status', [
                    'success' => 0,
                    'msg' => __('superadmin::lang.scheme_has_no_gst'),
                ]);
            }
            $businesses = Business::where('id', '!=', $template_business_id)
                ->where('tax_number_1', $master_scheme->gst_number)
                ->get();
        } else {
            $businesses = Business::where('id', '!=', $template_business_id)
                ->whereIn('id', (array) $request->input('business_ids', []))
                ->get();
        }

        if ($businesses->isEmpty()) {
            return redirect()->back()->with('status', [
                'success' => 0,
                'msg' => __('superadmin::lang.no_business_to_assign'),
            ]);
        }

        $assigned = [];
        $skipped = [];
        foreach ($businesses as $business) {
            // GST guard: a scheme that declares a GSTIN may only be
            // attached to stores carrying the same GSTIN — the whole
            // point of the shared series is one series per GSTIN.
            if (! empty($master_scheme->gst_number)
                && $business->tax_number_1 !== $master_scheme->gst_number) {
                $skipped[] = $business->name;
                continue;
            }

            DB::transaction(function () use ($master_scheme, $master_layout, $business) {
                self::attachToBusiness($master_scheme, $master_layout, $business);
            });
            $assigned[] = $business->name;
        }

        $msg = __('superadmin::lang.invoice_assigned_to', ['names' => implode(', ', $assigned) ?: '-']);
        if (! empty($skipped)) {
            $msg .= ' | '.__('superadmin::lang.skipped_gst_mismatch', ['names' => implode(', ', $skipped)]);
        }

        return redirect()->back()->with('status', [
            'success' => ! empty($assigned) ? 1 : 0,
            'msg' => $msg,
        ]);
    }

    /**
     * Create/refresh the mirror scheme + layout inside $business and
     * point its locations at them. Idempotent; also used by the
     * business-creation auto-attach.
     */
    public static function attachToBusiness(InvoiceScheme $master_scheme, InvoiceLayout $master_layout, Business $business)
    {
        // Run atomically even when invoked outside a transaction
        // (e.g. the business-creation auto-attach hook).
        if (DB::transactionLevel() == 0) {
            return DB::transaction(function () use ($master_scheme, $master_layout, $business) {
                return self::attachToBusiness($master_scheme, $master_layout, $business);
            });
        }

        // The invoice prints "tax_label_1: tax_number_1" — a store
        // joining a GST series must carry the GSTIN caption.
        if (! empty($business->tax_number_1) && empty($business->tax_label_1)) {
            $business->tax_label_1 = 'GSTIN';
            $business->save();
        }

        // ----- Scheme mirror -----
        $mirror_scheme = InvoiceScheme::where('business_id', $business->id)
            ->where('master_invoice_scheme_id', $master_scheme->id)
            ->first();

        $scheme_fields = [
            'name' => $master_scheme->name,
            'scheme_type' => $master_scheme->scheme_type,
            'number_type' => $master_scheme->number_type,
            'prefix' => $master_scheme->prefix,
            'start_number' => $master_scheme->start_number,
            'total_digits' => $master_scheme->total_digits,
            'gst_number' => $master_scheme->gst_number,
            'state_name' => $master_scheme->state_name,
            'is_default' => 1,
        ];

        if (empty($mirror_scheme)) {
            $mirror_scheme = InvoiceScheme::create(array_merge($scheme_fields, [
                'business_id' => $business->id,
                'master_invoice_scheme_id' => $master_scheme->id,
                'invoice_count' => 0,
            ]));
        } else {
            $mirror_scheme->fill($scheme_fields)->save();
        }

        // ----- Layout mirror -----
        // Copied via the query builder with RAW attributes: several
        // layout columns are array-cast on the model, and filling
        // them with the master's raw JSON through Eloquent would
        // double-encode them.
        $mirror_layout = InvoiceLayout::where('business_id', $business->id)
            ->where('master_invoice_layout_id', $master_layout->id)
            ->first();

        $layout_fields = self::layoutCopyFields($master_layout);
        $layout_fields['is_default'] = 1;
        $layout_fields['updated_at'] = now();

        if (empty($mirror_layout)) {
            $mirror_layout_id = DB::table('invoice_layouts')->insertGetId(array_merge($layout_fields, [
                'business_id' => $business->id,
                'master_invoice_layout_id' => $master_layout->id,
                'created_at' => now(),
            ]));
            $mirror_layout = InvoiceLayout::find($mirror_layout_id);
        } else {
            DB::table('invoice_layouts')->where('id', $mirror_layout->id)->update($layout_fields);
        }

        // Only one default layout per business.
        InvoiceLayout::where('business_id', $business->id)
            ->where('id', '!=', $mirror_layout->id)
            ->update(['is_default' => 0]);

        // ----- Point every location (stores have exactly one) -----
        // MUST happen before deleting old schemes: the FKs
        // business_locations.invoice_scheme_id / invoice_layout_id
        // are ON DELETE CASCADE — deleting a scheme still referenced
        // by a location would delete the LOCATION itself.
        BusinessLocation::where('business_id', $business->id)
            ->update([
                'invoice_scheme_id' => $mirror_scheme->id,
                'sale_invoice_scheme_id' => $mirror_scheme->id,
                'invoice_layout_id' => $mirror_layout->id,
                'sale_invoice_layout_id' => $mirror_layout->id,
            ]);

        // Remove the store's other schemes: numbering outside the
        // shared series must be impossible. Transactions store
        // invoice_no as a string (no FK), so history is unaffected.
        // Extra belt: never delete a scheme any location still
        // references (see the CASCADE note above).
        InvoiceScheme::where('business_id', $business->id)
            ->where('id', '!=', $mirror_scheme->id)
            ->whereNotIn('id', function ($q) {
                $q->select('invoice_scheme_id')
                    ->from('business_locations')
                    ->whereNotNull('invoice_scheme_id');
            })
            ->delete();

        return [$mirror_scheme, $mirror_layout];
    }

    /**
     * Start a new series on a master scheme (e.g. a new financial
     * year): set a new prefix and reset the counter. Mirrors are
     * format-synced in one bulk query.
     */
    public function resetSeries(Request $request)
    {
        $scheme = InvoiceScheme::where('business_id', self::templateBusinessId())
            ->findOrFail($request->input('scheme_id'));

        $scheme->prefix = (string) $request->input('prefix');
        $scheme->start_number = (int) ($request->input('start_number') ?: 1);
        $scheme->invoice_count = 0;
        $scheme->save();

        self::syncMirrorsForScheme($scheme);

        return redirect()->back()->with('status', [
            'success' => 1,
            'msg' => __('superadmin::lang.series_reset', ['scheme' => $scheme->name]),
        ]);
    }

    /**
     * Push a master scheme's FORMAT fields to all its mirrors in one
     * bulk query (scale-safe at thousands of stores). The counter is
     * never synced — it lives only on the master.
     */
    public static function syncMirrorsForScheme(InvoiceScheme $master)
    {
        InvoiceScheme::where('master_invoice_scheme_id', $master->id)
            ->update([
                'name' => $master->name,
                'scheme_type' => $master->scheme_type,
                'number_type' => $master->number_type,
                'prefix' => $master->prefix,
                'start_number' => $master->start_number,
                'total_digits' => $master->total_digits,
                'gst_number' => $master->gst_number,
                'state_name' => $master->state_name,
            ]);
    }

    /**
     * Push a master layout's fields to all its mirrors in one bulk
     * query (raw attributes via the query builder — see the casting
     * note in attachToBusiness).
     */
    public static function syncMirrorsForLayout(InvoiceLayout $master)
    {
        DB::table('invoice_layouts')
            ->where('master_invoice_layout_id', $master->id)
            ->update(array_merge(self::layoutCopyFields($master), ['updated_at' => now()]));
    }

    /**
     * All copyable fields of an invoice layout: everything except
     * identity/ownership columns.
     */
    private static function layoutCopyFields(InvoiceLayout $layout)
    {
        $exclude = ['id', 'business_id', 'is_default', 'master_invoice_layout_id', 'created_at', 'updated_at'];

        return collect($layout->getAttributes())
            ->except($exclude)
            ->toArray();
    }
}
