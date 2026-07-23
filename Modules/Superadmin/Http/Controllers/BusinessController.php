<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Business;
use App\Contact;
use App\Product;
use App\Transaction;
use App\User;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use App\VariationLocationDetails;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Notifications\PasswordUpdateNotification;
use Spatie\Permission\Models\Permission;
use Yajra\DataTables\Facades\DataTables;

class BusinessController extends BaseController
{
    protected $businessUtil;

    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(BusinessUtil $businessUtil, ModuleUtil $moduleUtil)
    {
        $this->businessUtil = $businessUtil;
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $date_today = \Carbon::today();
            $businesses = Business::leftjoin('subscriptions AS s', function ($join) use ($date_today) {
                $join->on('business.id', '=', 's.business_id')
                                    ->whereDate('s.start_date', '<=', $date_today)
                                    ->whereDate('s.end_date', '>=', $date_today)
                                    ->where('s.status', 'approved');
            })
                            ->leftjoin('packages as p', 's.package_id', '=', 'p.id')
                            ->leftjoin('business_locations as bl', 'business.id', '=', 'bl.business_id')
                            ->leftjoin('users as u', 'u.id', '=', 'business.owner_id')
                            ->leftjoin('users as creator', 'creator.id', '=', 'business.created_by')
                            ->select(
                                    'business.id',
                                    'business.name',
                                    DB::raw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as owner_name"),
                                    'u.email as owner_email',
                                    'u.contact_number',
                                    'bl.mobile',
                                    'bl.alternate_number',
                                    'bl.city',
                                    'bl.state',
                                    'bl.country',
                                    'bl.landmark',
                                    'bl.zip_code',
                                    'business.is_active',
                                    's.start_date',
                                    's.end_date',
                                    'p.name as package_name',
                                    'business.created_at',
                                    DB::raw("CONCAT(COALESCE(creator.surname, ''), ' ', COALESCE(creator.first_name, ''), ' ', COALESCE(creator.last_name, '')) as biz_creator")
                                )->groupBy('business.id');

            if (! empty(request()->package_id)) {
                $businesses->where('p.id', request()->package_id);
            }

            $subscription_status = request()->subscription_status;
            if ($subscription_status == 30) {
                $businesses->whereDate('s.end_date', '<=', \Carbon::today()->addDays(30));
            } elseif ($subscription_status == 7) {
                $businesses->whereDate('s.end_date', '<=', \Carbon::today()->addDays(7));
            } elseif ($subscription_status == 3) {
                $businesses->whereDate('s.end_date', '<=', \Carbon::today()->addDays(3));
            } elseif ($subscription_status == 'expired') {
                $businesses->where(function ($q) {
                    $q->whereDate('s.end_date', '<', \Carbon::today())
                    ->orWhereNull('s.end_date');
                });
            } elseif ($subscription_status == 'subscribed') {
                $businesses->whereNotNull('s.start_date');
            }

            $is_active = request()->is_active;
            if ($is_active == 'active') {
                $businesses->where('business.is_active', 1);
            } elseif ($is_active == 'inactive') {
                $businesses->where('business.is_active', 0);
            }

            $last_transaction_date = request()->last_transaction_date;
            $query = $this->filterTransactionDate($businesses, $last_transaction_date, '>');

            $no_transaction_since = request()->no_transaction_since;

            $query = $this->filterTransactionDate($businesses, $no_transaction_since, '=');

            return Datatables::of($query)
                ->addColumn('address', '{{$city}}, {{$state}}, {{$country}} {{$landmark}}, {{$zip_code}}')
                ->addColumn('business_contact_number', '{{$mobile}} @if(!empty($alternate_number)), {{$alternate_number}}@endif')
                ->editColumn('is_active', '@if($is_active == 1) <span class="label bg-green">@lang("business.is_active")</span> @else <span class="label bg-gray">@lang("lang_v1.inactive")</span> @endif')
                ->addColumn('action', function ($row) {
                    $html = '<a href="'.
                            action([\Modules\Superadmin\Http\Controllers\BusinessController::class, 'show'], [$row->id]).'"
                                class="btn btn-info btn-xs">'.__('superadmin::lang.manage').'</a>
                            <button type="button" class="btn btn-primary btn-xs btn-modal" data-href="'.action([\Modules\Superadmin\Http\Controllers\SuperadminSubscriptionsController::class, 'create'], ['business_id' => $row->id]).'" data-container=".view_modal">'
                                  .__('superadmin::lang.add_subscription').'</button>';

                    if ($row->is_active == 1) {
                        $html .= ' <a href="'.action([\Modules\Superadmin\Http\Controllers\BusinessController::class, 'toggleActive'], [$row->id, 0]).'"
                                    class="btn btn-danger btn-xs link_confirmation">'.__('lang_v1.deactivate').'
                                </a>';
                    } else {
                        $html .= ' <a href="'.action([\Modules\Superadmin\Http\Controllers\BusinessController::class, 'toggleActive'], [$row->id, 1]).'"
                                    class="btn btn-success btn-xs link_confirmation">'.__('lang_v1.activate').'
                                </a>';
                    }

                    if (request()->session()->get('user.business_id') != $row->id) {
                        $html .= ' <a href="'.action([\Modules\Superadmin\Http\Controllers\BusinessController::class, 'destroy'], [$row->id]).'"
                                    class="btn btn-danger btn-xs delete_business_confirmation">'.__('messages.delete').'</a>';
                    }

                    return $html;
                })
                ->filterColumn('owner_name', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(surname, ''), ' ', COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", ["%{$keyword}%"]);
                })
                ->filterColumn('address', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(city, ''), ', ', COALESCE(state, ''), ', ', COALESCE(country, ''), ', ', COALESCE(landmark, ''), ', ', COALESCE(zip_code, '')) like ?", ["%{$keyword}%"]);
                })
                ->filterColumn('business_contact_number', function ($query, $keyword) {
                    $query->where(function ($q) use ($keyword) {
                        $q->where('bl.mobile', 'like', "%{$keyword}%")
                        ->orWhere('bl.alternate_number', 'like', "%{$keyword}%");
                    });
                })
                ->addColumn('current_subscription', '{{$package_name ?? ""}} @if(!empty($start_date) && !empty($end_date)) ({{@format_date($start_date)}} - {{@format_date($end_date)}}) @endif')
                ->editColumn('created_at', '{{@format_datetime($created_at)}}')
                ->rawColumns(['action', 'is_active', 'created_at'])
                ->make(true);
        }

        $business_id = request()->session()->get('user.business_id');

        $packages = Package::listPackages()->pluck('name', 'id');

        $subscription_statuses = [
            'subscribed' => __('superadmin::lang.subscribed'),
            'expired' => __('report.expired'),
            '30' => __('superadmin::lang.expiring_in_one_month'),
            '7' => __('superadmin::lang.expiring_in_7_days'),
            '3' => __('superadmin::lang.expiring_in_3_days'),
        ];

        $last_transaction_date = [
            'today' => __('home.today'),
            'yesterday' => __('superadmin::lang.yesterday'),
            'this_week' => __('home.this_week'),
            'this_month' => __('home.this_month'),
            'last_month' => __('superadmin::lang.last_month'),
            'this_year' => __('superadmin::lang.this_year'),
            'last_year' => __('superadmin::lang.last_year'),
        ];

        return view('superadmin::business.index')
            ->with(compact('business_id', 'packages', 'subscription_statuses', 'last_transaction_date'));
    }

    private function filterTransactionDate($query, $filter, $operator)
    {
        if ($filter == 'today') {
            $today = \Carbon::today()->format('Y-m-d');
            $query->whereRaw("(SELECT COUNT(id) FROM transactions as t WHERE t.business_id = business.id AND DATE(t.transaction_date) = '$today') $operator 0");
        } elseif ($filter == 'yesterday') {
            $yesterday = \Carbon::yesterday()->format('Y-m-d');
            $query->whereRaw("(SELECT COUNT(id) FROM transactions as t WHERE t.business_id = business.id AND DATE(t.transaction_date) >= '$yesterday') $operator 0");
        } elseif ($filter == 'this_week') {
            $this_week = \Carbon::today()->subDays(7)->format('Y-m-d');
            $query->whereRaw("(SELECT COUNT(id) FROM transactions as t WHERE t.business_id = business.id AND DATE(t.transaction_date) >= '$this_week') $operator 0");
        } elseif ($filter == 'this_month') {
            $this_month = \Carbon::today()->firstOfMonth()->format('Y-m-d');
            $query->whereRaw("(SELECT COUNT(id) FROM transactions as t WHERE t.business_id = business.id AND DATE(t.transaction_date) >= '$this_month') $operator 0");
        } elseif ($filter == 'last_month') {
            $last_month = \Carbon::today()->subDays(30)->firstOfMonth()->format('Y-m-d');
            $query->whereRaw("(SELECT COUNT(id) FROM transactions as t WHERE t.business_id = business.id AND DATE(t.transaction_date) >= '$last_month') $operator 0");
        } elseif ($filter == 'this_year') {
            $this_year = \Carbon::today()->firstOfYear()->format('Y-m-d');
            $query->whereRaw("(SELECT COUNT(id) FROM transactions as t WHERE t.business_id = business.id AND DATE(t.transaction_date) >= '$this_year') $operator 0");
        } elseif ($filter == 'last_year') {
            $last_year = \Carbon::today()->subYear()->firstOfYear()->format('Y-m-d');
            $query->whereRaw("(SELECT COUNT(id) FROM transactions as t WHERE t.business_id = business.id AND DATE(t.transaction_date) >= '$last_year') $operator 0");
        }

        return $query;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $currencies = $this->businessUtil->allCurrencies();
        $timezone_list = $this->businessUtil->allTimeZones();

        $accounting_methods = $this->businessUtil->allAccountingMethods();

        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[$i] = __('business.months.'.$i);
        }

        $is_admin = true;

        $packages = Package::active()->orderby('sort_order')->pluck('name', 'id');
        $gateways = $this->_payment_gateways();

        // Master suppliers = suppliers in the super admin's own business
        // (Contacts with type='supplier' under the logged-in super admin).
        $super_admin_business_id = session('user.business_id');
        $common_suppliers = Contact::where('business_id', $super_admin_business_id)
            ->whereIn('type', ['supplier', 'both'])
            ->orderBy('name')
            ->select(
                'id',
                'name',
                'contact_id',
                'supplier_business_name',
                'email',
                'mobile'
            )
            ->get()
            ->mapWithKeys(function ($c) {
                // Build a human-friendly label. Prefer the contact `name`,
                // then the business/trade name, then a numeric id fallback.
                $label = trim((string) $c->name);
                if ($label === '' && ! empty($c->supplier_business_name)) {
                    $label = trim((string) $c->supplier_business_name);
                }
                if ($label === '') {
                    $label = 'Supplier #' . $c->id;
                }
                if (! empty($c->contact_id)) {
                    $label .= ' (' . $c->contact_id . ')';
                }
                return [$c->id => $label];
            });

        // Pre-created (unassigned) users available to be picked as
        // the owner of the new business. We need the full record so the
        // "Owner information" block can be auto-populated from the
        // selected user (surname, first_name, last_name, username, email).
        $precreated_users = User::whereNull('business_id')
            ->where('user_type', 'user')
            ->where('allow_login', 0)
            ->orderBy('id')
            ->select('id', 'surname', 'first_name', 'last_name', 'username', 'email')
            ->get();
        $precreated_users_options = $precreated_users->mapWithKeys(function ($u) {
            $label = trim(($u->surname ?? '').' '.($u->first_name ?? '').' '.($u->last_name ?? '').' ('.$u->username.')');
            return [$u->id => $label];
        });

        return view('superadmin::business.create')
            ->with(compact(
                'currencies',
                'timezone_list',
                'accounting_methods',
                'months',
                'is_admin',
                'packages',
                'gateways',
                'common_suppliers',
                'precreated_users',
                'precreated_users_options'
            ));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        // The management-assigned store unique number must be unique
        // across all businesses when provided.
        $request->validate([
            'store_unique_number' => 'nullable|string|max:191|unique:business,store_unique_number',
            'auto_po_frequency_days' => 'nullable|integer|min:1|max:30',
        ]);

        try {
            DB::beginTransaction();

            $owner_source = $request->input('owner_source', 'new');
            $precreated_user_id = $request->input('precreated_user_id');
            $is_assigning_existing = $owner_source === 'existing' && ! empty($precreated_user_id);

            if ($is_assigning_existing) {
                // Use a pre-created user. Validate they are still
                // unassigned before consuming the request.
                $user = User::whereNull('business_id')
                    ->where('user_type', 'user')
                    ->where('allow_login', 0)
                    ->findOrFail($precreated_user_id);
            } else {
                // Create owner.
                $owner_details = $request->only(['surname', 'first_name', 'last_name', 'username', 'email', 'password']);
                $owner_details['language'] = env('APP_LOCALE');

                $user = User::create_user($owner_details);
            }

            $business_details = $request->only(['name', 'store_unique_number', 'start_date', 'currency_id', 'tax_label_1', 'tax_number_1', 'tax_label_2', 'tax_number_2', 'time_zone', 'accounting_method', 'fy_start_month', 'auto_po_frequency_days']);

            // Normalise empty string to null so the unique index treats
            // "not set" stores consistently (multiple NULLs are allowed).
            if (empty($business_details['store_unique_number'])) {
                $business_details['store_unique_number'] = null;
            }

            // Auto-PO frequency: normalise blanks to null so an unset
            // store cleanly inherits the chain-wide default.
            $business_details['auto_po_frequency_days'] = ! empty($business_details['auto_po_frequency_days'])
                ? (int) $business_details['auto_po_frequency_days'] : null;

            $business_location = $request->only(['name', 'country', 'state', 'city', 'zip_code', 'landmark', 'website', 'mobile', 'alternate_number']);

            //Create the business
            $business_details['owner_id'] = $user->id;
            if (! empty($business_details['start_date'])) {
                $business_details['start_date'] = $this->businessUtil->uf_date($business_details['start_date']);
            }

            //upload logo
            $logo_name = $this->businessUtil->uploadFile($request, 'business_logo', 'business_logos', 'image');
            if (! empty($logo_name)) {
                $business_details['logo'] = $logo_name;
            }

            //default enabled modules
            $business_details['enabled_modules'] = ['purchases', 'add_sale', 'pos_sale', 'stock_transfers', 'stock_adjustment', 'expenses'];

            //created_by
            $business_details['created_by'] = $request->session()->get('user.id');

            $business = $this->businessUtil->createNewBusiness($business_details);

            //Update user with business id and enable login
            $user->business_id = $business->id;
            $user->allow_login = 1;
            $user->status = 'active';
            $user->save();

            $this->businessUtil->newBusinessDefaultResources($business->id, $user->id);
            $new_location = $this->businessUtil->addLocation($business->id, $business_location);

            //create new permission with the new location
            Permission::create(['name' => 'location.'.$new_location->id]);

            // If the user was pre-created with a role name, apply
            // that role (resolved in the new business) INSTEAD of the
            // default Admin#<business_id>. This carries the role
            // features over to the new business.
            if ($is_assigning_existing && ! empty($user->pre_create_role)) {
                $role_in_new_business = \Spatie\Permission\Models\Role::where(
                    'name',
                    $user->pre_create_role . '#' . $business->id
                )->first();

                // Defensive fallback: if the new business does
                // not have this role yet (e.g. it was created
                // before role cloning was added) create it now
                // by cloning from the super admin's business so
                // the pre_create_role promise is honoured. The
                // clone is permission-faithful: every permission
                // attached to the template role is also attached
                // to the new role.
                if (! $role_in_new_business) {
                    $role_in_new_business = $this->moduleUtil->cloneRoleFromTemplateByName(
                        $business->id,
                        $user->pre_create_role
                    );
                }

                if ($role_in_new_business) {
                    // Detach Admin#<business_id> and attach the
                    // selected role instead. If the selected role
                    // is the same as Admin, no-op.
                    $admin_role = \Spatie\Permission\Models\Role::where(
                        'name',
                        'Admin#' . $business->id
                    )->first();

                    if ($admin_role && $admin_role->id !== $role_in_new_business->id) {
                        $user->removeRole($admin_role->name);
                    }

                    $user->assignRole($role_in_new_business->name);

                    // The Admin#<id> role passes every permission
                    // check via Gate::before, but any other role
                    // leaves the new owner without location access:
                    // permitted_locations() returns an empty array
                    // and every location-filtered screen (product
                    // list, sells list, reports) goes blank. Grant
                    // the owner access to all locations of their
                    // own business explicitly.
                    if (! $role_in_new_business->hasPermissionTo('access_all_locations')) {
                        $user->givePermissionTo('access_all_locations');
                    }

                    // Clear the pre_create_role marker so the
                    // assignment is one-time only.
                    $user->pre_create_role = null;
                    $user->save();
                }
            }

            $subscription_details = $request->only(['package_id', 'paid_via', 'payment_transaction_id']);

            //Add subscription if present
            if (! empty($subscription_details['package_id']) && ! empty($subscription_details['paid_via'])) {
                $subscription = $this->_add_subscription($business->id, $subscription_details['package_id'], $subscription_details['paid_via'], $subscription_details['payment_transaction_id'], $request->session()->get('user.id'), true);
            }

            DB::commit();

            // "Apply this frequency to ALL stores" — the superadmin can set
            // one uniform auto-PO cadence chain-wide. This overwrites every
            // business's per-store value (including the template default).
            if ($request->boolean('apply_po_freq_chain_wide') && ! empty($business->auto_po_frequency_days)) {
                Business::query()->update(['auto_po_frequency_days' => (int) $business->auto_po_frequency_days]);
            }

            //Sync master products to the new business (defensive: never block business creation)
            try {
                SuperadminProductController::syncAllProductsToBusiness($business);
            } catch (\Exception $e) {
                \Log::error('Master product sync to new superadmin-created business failed: ' . $e->getMessage(), [
                    'business_id' => $business->id,
                ]);
            }

            //Assign the suppliers selected during business creation.
            //Each selected supplier is a Contact id from the super admin's
            //own business — we clone it into the new business so it shows
            //up in the business's supplier list.
            $supplier_ids = (array) $request->input('supplier_ids', []);
            $supplier_ids = array_values(array_unique(array_filter(array_map('intval', $supplier_ids))));
            $super_admin_business_id = $request->session()->get('user.business_id');
            $user_id = $request->session()->get('user.id');
            if (!empty($supplier_ids)) {
                try {
                    $master_suppliers = Contact::where('business_id', $super_admin_business_id)
                        ->whereIn('id', $supplier_ids)
                        ->get();
                    foreach ($master_suppliers as $master) {
                        $master->cloneToBusinessAsSupplier($business->id, $user_id);
                    }
                } catch (\Exception $e) {
                    \Log::error('Supplier assignment during business creation failed: ' . $e->getMessage(), [
                        'business_id' => $business->id,
                    ]);
                }
            }

            //Auto-attach the state-wise invoice scheme/layout: if the
            //new store carries a GST number and a master scheme is
            //registered for that GSTIN, the store joins that shared
            //serial invoice series immediately — no manual step.
            try {
                if (!empty($business->tax_number_1)) {
                    $master_scheme = \App\InvoiceScheme::where('business_id', InvoiceAssignmentController::templateBusinessId())
                        ->where('gst_number', $business->tax_number_1)
                        ->first();
                    if (!empty($master_scheme)) {
                        $master_layout = \App\InvoiceLayout::where('business_id', InvoiceAssignmentController::templateBusinessId())
                            ->where('is_default', 1)
                            ->first();
                        if (!empty($master_layout)) {
                            InvoiceAssignmentController::attachToBusiness($master_scheme, $master_layout, $business);
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Invoice scheme auto-attach during business creation failed: ' . $e->getMessage(), [
                    'business_id' => $business->id,
                ]);
            }

            //Module function to be called after after business is created
            if (config('app.env') != 'demo') {
                $this->moduleUtil->getModuleData('after_business_created', ['business' => $business]);
            }

            $output = ['success' => 1,
                'msg' => $is_assigning_existing
                    ? __('superadmin::lang.user_assigned_to_business')
                    : __('business.business_created_succesfully'),
            ];

            return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\BusinessController::class, 'index'])
                ->with('status', $output);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];

            return back()->with('status', $output)->withInput();
        }
    }

    /**
     * Show the specified resource.
     *
     * @return Response
     */
    public function show(Request $request, $business_id)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $business = Business::with(['currency', 'locations', 'subscriptions', 'owner'])->findOrFail($business_id);

        $created_id = $business->created_by;

        $created_by = ! empty($created_id) ? User::find($created_id) : null;

        // Suppliers the superadmin has assigned to this store (clones with
        // common_supplier_id set). Auto-POs go to the assigned supplier, so
        // surface it read-only under the frequency box.
        $assigned_suppliers = Contact::where('business_id', $business_id)
            ->whereIn('type', ['supplier', 'both'])
            ->whereNotNull('common_supplier_id')
            ->orderBy('id')
            ->pluck('name');

        // The value actually in effect (own override, else chain default).
        $effective_auto_po_frequency = $business->effectiveAutoPoFrequencyDays();

        return view('superadmin::business.show')
            ->with(compact('business', 'created_by', 'assigned_suppliers', 'effective_auto_po_frequency'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit()
    {
        return view('superadmin::edit');
    }

    /**
     * Display a page where the super admin can assign / unassign
     * common suppliers (Contacts in the super admin's own business)
     * to a single existing business.
     */
    public function manageSuppliers(Request $request, $business_id)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $business = Business::findOrFail($business_id);

        // Master suppliers live in the super admin's own business.
        $super_admin_business_id = $request->session()->get('user.business_id');
        $all_suppliers = Contact::where('business_id', $super_admin_business_id)
            ->whereIn('type', ['supplier', 'both'])
            ->orderBy('name')
            ->get();

        // Suppliers currently assigned = master supplier ids that have
        // been cloned into this business.
        $assigned_ids = Contact::where('business_id', $business->id)
            ->whereNotNull('common_supplier_id')
            ->pluck('common_supplier_id')
            ->toArray();

        // Build a display label so empty-name suppliers still show up in the list.
        $all_suppliers->each(function ($c) {
            $label = trim((string) $c->name);
            if ($label === '' && ! empty($c->supplier_business_name)) {
                $label = trim((string) $c->supplier_business_name);
            }
            if ($label === '') {
                $label = 'Supplier #' . $c->id;
            }
            if (! empty($c->contact_id)) {
                $label .= ' (' . $c->contact_id . ')';
            }
            $c->display_name = $label;
        });

        return view('superadmin::business.manage_suppliers', compact('business', 'all_suppliers', 'assigned_ids'));
    }

    /**
     * Sync the supplier assignment set for a single business.
     * Accepts the FULL set of master supplier ids (Contact ids from the
     * super admin's own business) the business should be assigned to.
     * Suppliers not in the list are unassigned (clones are kept but
     * unlinked so historical purchases stay valid).
     */
    public function syncSuppliers(Request $request, $business_id)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business = Business::findOrFail($business_id);
            $super_admin_business_id = $request->session()->get('user.business_id');
            $user_id = $request->session()->get('user.id');

            $new_ids = array_values(array_unique(array_map('intval', (array) $request->input('supplier_ids', []))));

            // Currently assigned = master ids with a clone in this business.
            $current_ids = Contact::where('business_id', $business->id)
                ->whereNotNull('common_supplier_id')
                ->pluck('common_supplier_id')
                ->toArray();

            $to_add = array_diff($new_ids, $current_ids);
            $to_remove = array_diff($current_ids, $new_ids);

            // Load all master suppliers we may need in one query.
            $needed_ids = array_unique(array_merge($to_add, $to_remove));
            $masters = Contact::where('business_id', $super_admin_business_id)
                ->whereIn('id', $needed_ids)
                ->get()
                ->keyBy('id');

            foreach ($to_add as $sid) {
                if (isset($masters[$sid])) {
                    $masters[$sid]->cloneToBusinessAsSupplier($business->id, $user_id);
                }
            }

            foreach ($to_remove as $sid) {
                if (isset($masters[$sid])) {
                    $masters[$sid]->unlinkFromBusiness($business->id);
                }
            }

            $output = [
                'success' => true,
                'msg' => __('superadmin::lang.common_suppliers_synced'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return response()->json($output);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    /**
     * Save the per-store sale-return window (number of days after
     * purchase within which a customer may return at that store).
     * 0 / blank clears the limit (unlimited returns).
     */
    public function saveReturnPolicy(Request $request, $business_id)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $business = Business::findOrFail($business_id);
        $days = (int) $request->input('sell_return_period_days');
        $business->sell_return_period_days = $days > 0 ? $days : null;
        $business->save();

        return redirect()->back()->with('status', [
            'success' => 1,
            'msg' => __('superadmin::lang.return_policy_updated', ['business' => $business->name]),
        ]);
    }

    public function update(Request $request)
    {
    }

    /**
     * Update the management-assigned store unique number for a
     * business (from the business detail page). Blank clears it;
     * must stay unique across businesses.
     */
    public function saveStoreNumber(Request $request, $business_id)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'store_unique_number' => 'nullable|string|max:191|unique:business,store_unique_number,'.$business_id.',id',
        ]);

        $business = Business::findOrFail($business_id);
        $number = trim((string) $request->input('store_unique_number'));
        $business->store_unique_number = $number !== '' ? $number : null;
        $business->save();

        return redirect()->back()->with('status', [
            'success' => 1,
            'msg' => __('superadmin::lang.store_number_updated', ['business' => $business->name]),
        ]);
    }

    /**
     * Save the per-store auto-PO frequency (days between automatic
     * purchase orders) from the business detail page. Blank/0 clears the
     * per-store override so the store inherits the chain-wide default.
     * If "apply to all stores" is ticked, the value is pushed to every
     * business (uniform chain-wide cadence).
     */
    public function saveAutoPoFrequency(Request $request, $business_id)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'auto_po_frequency_days' => 'nullable|integer|min:1|max:30',
        ]);

        $business = Business::findOrFail($business_id);
        $days = (int) $request->input('auto_po_frequency_days');
        $days = ($days >= 1 && $days <= 30) ? $days : null;

        if ($request->boolean('apply_po_freq_chain_wide') && ! empty($days)) {
            // Uniform cadence for every store.
            Business::query()->update(['auto_po_frequency_days' => $days]);
            $msg = __('superadmin::lang.auto_po_frequency_updated_all');
        } else {
            $business->auto_po_frequency_days = $days;
            $business->save();
            $msg = __('superadmin::lang.auto_po_frequency_updated', ['business' => $business->name]);
        }

        return redirect()->back()->with('status', [
            'success' => 1,
            'msg' => $msg,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $notAllowed = $this->businessUtil->notAllowedInDemo();
            if (! empty($notAllowed)) {
                return $notAllowed;
            }

            //Check if logged in busines id is same as deleted business then not allowed.
            $business_id = request()->session()->get('user.business_id');
            if ($business_id == $id) {
                $output = ['success' => 0, 'msg' => __('superadmin.lang.cannot_delete_current_business')];

                return back()->with('status', $output);
            }

            DB::beginTransaction();

            //Delete related products & transactions.
            $products_id = Product::where('business_id', $id)->pluck('id')->toArray();
            if (! empty($products_id)) {
                VariationLocationDetails::whereIn('product_id', $products_id)->delete();
            }
            Transaction::where('business_id', $id)->delete();

            Business::where('id', $id)
                ->delete();

            DB::commit();

            $output = ['success' => 1, 'msg' => __('lang_v1.success')];

            return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\BusinessController::class, 'index'])
                ->with('status', $output);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];

            return back()->with('status', $output)->withInput();
        }
    }

    /**
     * Changes the activation status of a business.
     *
     * @return Response
     */
    public function toggleActive(Request $request, $business_id, $is_active)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $notAllowed = $this->businessUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        Business::where('id', $business_id)
            ->update(['is_active' => $is_active]);

        $output = ['success' => 1,
            'msg' => __('lang_v1.success'),
        ];

        return back()->with('status', $output);
    }

    /**
     * Shows user list for a particular business
     *
     * @return Response
     */
    public function usersList($business_id)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $user_id = request()->session()->get('user.id');

            $users = User::where('business_id', $business_id)
                        ->where('id', '!=', $user_id)
                        ->where('is_cmmsn_agnt', 0)
                        ->select(['id', 'username',
                            DB::raw("CONCAT(COALESCE(surname, ''), ' ', COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name"), 'email', ]);

            return Datatables::of($users)
                ->addColumn(
                    'role',
                    function ($row) {
                        $role_name = $this->moduleUtil->getUserRoleName($row->id);

                        return $role_name;
                    }
                )
                ->addColumn(
                    'action',
                    '@can("user.update")
                        <a href="#" class="btn btn-xs btn-primary update_user_password" data-user_id="{{$id}}" data-user_name="{{$full_name}}"><i class="glyphicon glyphicon-edit"></i> @lang("superadmin::lang.update_password")</a>
                        &nbsp;
                        @if(!empty($username))
                        <a href="{{route("sign-in-as-user", $id)}}?save_current=true" class="btn btn-xs btn-success"><i class="fas fa-sign-in-alt"></i> @lang("lang_v1.login_as_username", ["username" => $username])</a>
                        @endif
                    @endcan'
                )
                ->filterColumn('full_name', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(surname, ''), ' ', COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", ["%{$keyword}%"]);
                })
                ->removeColumn('id')
                ->rawColumns(['action'])
                ->make(true);
        }
    }

    /**
     * Updates user password from superadmin
     *
     * @return Response
     */
    public function updatePassword(Request $request)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $notAllowed = $this->businessUtil->notAllowedInDemo();
            if (! empty($notAllowed)) {
                return $notAllowed;
            }

            $user = User::findOrFail($request->input('user_id'));
            $user->password = Hash::make($request->input('password'));
            $user->save();

            //Send password update notification
            if ($this->moduleUtil->IsMailConfigured()) {
                $user->notify(new PasswordUpdateNotification($request->input('password')));
            }

            $output = ['success' => 1,
                'msg' => __('superadmin::lang.password_updated_successfully'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }
}
