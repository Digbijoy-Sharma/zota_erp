<?php

namespace App\Http\Controllers;

use App\Contact;
use App\Transaction;
use App\Utils\Util;
use Illuminate\Http\Request;

/**
 * Supplier (warehouse) portal.
 *
 * A supplier login (users.user_type = 'user_supplier') sees every
 * Purchase Order raised to it ACROSS ALL STORES. Isolation in this app is
 * enforced manually per query by business_id; the supplier legitimately
 * spans businesses, so instead of a business_id clause we scope by the
 * supplier's identity: all store-local supplier clones share the same
 * contacts.common_supplier_id = the logged-in user's common_supplier_id.
 *
 * The supplier updates transactions.shipping_status (ordered -> packed ->
 * shipped -> delivered) to signal dispatch. It never touches
 * transactions.status, which the store's GRN recomputes from received
 * quantities — keeping the two workflows from fighting each other.
 */
class SupplierPortalController extends Controller
{
    protected $commonUtil;

    public function __construct(Util $commonUtil)
    {
        $this->commonUtil = $commonUtil;
    }

    /**
     * All store-local supplier clone ids for the logged-in supplier
     * (spans every business). Empty collection if unlinked.
     */
    protected function supplierContactIds()
    {
        $master_id = auth()->user()->common_supplier_id;
        if (empty($master_id)) {
            return collect();
        }

        // The master record itself + all clones that point back to it.
        return Contact::where('id', $master_id)
            ->orWhere('common_supplier_id', $master_id)
            ->pluck('id');
    }

    /**
     * Base PO query scoped to this supplier across all stores.
     */
    protected function poQuery()
    {
        return Transaction::where('type', 'purchase_order')
            ->whereIn('contact_id', $this->supplierContactIds());
    }

    public function dashboard()
    {
        if (! auth()->user()->can('supplier_portal.view_dashboard')) {
            abort(403, 'Unauthorized action.');
        }

        $shipping_statuses = $this->commonUtil->shipping_statuses();

        // Count POs per dispatch status (NULL shipping_status = not yet
        // actioned => bucket as 'ordered').
        $status_counts = [];
        foreach (array_keys($shipping_statuses) as $key) {
            $status_counts[$key] = 0;
        }
        $rows = $this->poQuery()
            ->selectRaw("COALESCE(NULLIF(shipping_status, ''), 'ordered') as ss, COUNT(*) as cnt")
            ->groupBy('ss')
            ->pluck('cnt', 'ss');
        foreach ($rows as $ss => $cnt) {
            $status_counts[$ss] = ($status_counts[$ss] ?? 0) + $cnt;
        }

        $total_pos = $this->poQuery()->count();
        $total_value = $this->poQuery()->sum('final_total');
        $store_count = (clone $this->poQuery())->distinct('business_id')->count('business_id');

        $recent_pos = $this->poQuery()
            ->with(['business', 'location'])
            ->orderBy('transaction_date', 'desc')
            ->limit(10)
            ->get();

        return view('supplier_portal.dashboard', compact(
            'status_counts',
            'shipping_statuses',
            'total_pos',
            'total_value',
            'store_count',
            'recent_pos'
        ));
    }

    public function purchaseOrders(Request $request)
    {
        if (! auth()->user()->can('supplier_portal.view_po')) {
            abort(403, 'Unauthorized action.');
        }

        $shipping_statuses = $this->commonUtil->shipping_statuses();

        $query = $this->poQuery()->with(['business', 'location']);

        // Optional filters.
        $filter_status = $request->input('shipping_status');
        if (! empty($filter_status) && array_key_exists($filter_status, $shipping_statuses)) {
            if ($filter_status === 'ordered') {
                $query->where(function ($q) {
                    $q->whereNull('shipping_status')->orWhere('shipping_status', '')->orWhere('shipping_status', 'ordered');
                });
            } else {
                $query->where('shipping_status', $filter_status);
            }
        }

        if (! empty($request->input('ref_no'))) {
            $query->where('ref_no', 'like', '%'.trim($request->input('ref_no')).'%');
        }

        $purchase_orders = $query->orderBy('transaction_date', 'desc')->paginate(25)->withQueryString();

        return view('supplier_portal.purchase_orders', compact('purchase_orders', 'shipping_statuses', 'filter_status'));
    }

    public function show($id)
    {
        if (! auth()->user()->can('supplier_portal.view_po')) {
            abort(403, 'Unauthorized action.');
        }

        $po = $this->poQuery()
            ->with(['business', 'location', 'contact', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.variations'])
            ->findOrFail($id);

        $shipping_statuses = $this->commonUtil->shipping_statuses();
        $can_update = auth()->user()->can('supplier_portal.update_po_status');

        return view('supplier_portal.show', compact('po', 'shipping_statuses', 'can_update'));
    }

    public function updateStatus(Request $request, $id)
    {
        if (! auth()->user()->can('supplier_portal.update_po_status')) {
            abort(403, 'Unauthorized action.');
        }

        $shipping_statuses = $this->commonUtil->shipping_statuses();

        $request->validate([
            'shipping_status' => 'required|in:'.implode(',', array_keys($shipping_statuses)),
        ]);

        // Scoped lookup: a supplier can only touch its own POs.
        $po = $this->poQuery()->findOrFail($id);

        $po->shipping_status = $request->input('shipping_status');
        if ($request->filled('shipping_details')) {
            $po->shipping_details = $request->input('shipping_details');
        }
        $po->save();

        return redirect()->back()->with('status', [
            'success' => 1,
            'msg' => __('lang_v1.updated_success'),
        ]);
    }
}
