<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\User;
use App\Utils\ModuleUtil;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;
use App\Events\UserCreatedOrModified;

class ManageUserController extends Controller
{
    /**
     * Constructor
     *
     * User management is performed centrally by the super admin.
     * Store-level users and business admins are NOT allowed to
     * access this controller. The super admin (configured in
     * config/constants.php -> administrator_usernames) is the only
     * user that can pass the `can('superadmin')` check.
     *
     * @param  Util  $commonUtil
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;

        $this->middleware(function ($request, $next) {
            if (! auth()->user()->can('superadmin')) {
                abort(403, 'Unauthorized action. Only the super admin can manage users.');
            }
            return $next($request);
        });
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! auth()->user()->can('user.view') && ! auth()->user()->can('user.create')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $user_id = request()->session()->get('user.id');

            $is_superadmin = auth()->user()->can('superadmin');

            // Status tab selected on the /users page. `all` shows
            // every user (current business + pre-created); `assigned`
            // shows only users who already have a business;
            // `precreated` shows only unassigned users waiting to be
            // attached to a new business.
            $status = request()->input('status', 'all');
            if (! in_array($status, ['all', 'assigned', 'precreated'], true)) {
                $status = 'all';
            }

            $baseSelect = [
                'users.id', 'users.username', 'users.business_id',
                DB::raw("CONCAT(COALESCE(users.surname, ''), ' ', COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) as full_name"),
                'users.email', 'users.allow_login', 'users.pre_create_role',
                'b.name as business_name',
            ];

            // Helper to build a single user query with the standard
            // scope + business-name join. We pull the join into a
            // closure so the column list stays identical across
            // both legs of the union.
            $buildUsersQuery = function ($businessFilter) use ($baseSelect) {
                $q = User::user()
                    ->where('is_cmmsn_agnt', 0)
                    ->leftJoin('business as b', 'b.id', '=', 'users.business_id')
                    ->select($baseSelect);

                if ($businessFilter === 'with') {
                    $q->whereNotNull('users.business_id');
                } elseif ($businessFilter === 'without') {
                    $q->whereNull('users.business_id');
                } else {
                    // 'any' - no business scope, used for the
                    // superadmin `all` view.
                }

                return $q;
            };

            if ($is_superadmin) {
                // Super admin can see every user in the chain.
                // - `assigned`   -> users with a business
                // - `precreated` -> users without a business
                // - `all`        -> both, deduped via the master/
                //                  clone relationship so the same
                //                  person does not appear twice.
                if ($status === 'assigned') {
                    $users = $buildUsersQuery('with');
                } elseif ($status === 'precreated') {
                    $users = $buildUsersQuery('without');
                } else {
                    $with = $buildUsersQuery('with');
                    $without = $buildUsersQuery('without');
                    $users = $with->union($without);
                }
            } else {
                // Non-superadmin: only their own business, ever.
                $users = $buildUsersQuery('with')
                    ->where('users.business_id', $business_id);
            }

            return Datatables::of($users)
                ->editColumn('username', function ($row) {
                    $html = $row->username;
                    // Show "login not allowed" badge for inactive users
                    if (empty($row->allow_login)) {
                        $html .= ' <span class="label bg-gray">' . __('lang_v1.login_not_allowed') . '</span>';
                    }
                    // Show a special badge for super admin: the
                    // user is "pre-created" (no business yet,
                    // waiting to be assigned to a new business).
                    if (empty($row->business_id)) {
                        $html .= ' <span class="label bg-orange">' . __('superadmin::lang.precreated') . '</span>';
                    }
                    return $html;
                })
                ->addColumn(
                    'role',
                    function ($row) {
                        // For a pre-created user, show the
                        // pre_create_role that will be applied at
                        // business-creation time.
                        if (empty($row->business_id)) {
                            $role = ! empty($row->pre_create_role)
                                ? $row->pre_create_role . ' <span class="text-muted">(' . __('superadmin::lang.will_be_assigned_to_new_business') . ')</span>'
                                : '<span class="text-muted">' . __('superadmin::lang.awaiting_business') . '</span>';
                            return $role;
                        }
                        return $this->moduleUtil->getUserRoleName($row->id);
                    }
                )
                ->addColumn(
                    'business_name',
                    function ($row) {
                        // Pre-created users have no business yet.
                        if (empty($row->business_id)) {
                            return '<span class="text-muted">—</span>';
                        }
                        return e($row->business_name ?: ('#' . $row->business_id));
                    }
                )
                ->addColumn(
                    'action',
                    '@can("user.update")
                        <a href="{{action(\'App\Http\Controllers\ManageUserController@edit\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</a>
                        &nbsp;
                    @endcan
                    @can("user.view")
                    <a href="{{action(\'App\Http\Controllers\ManageUserController@show\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-info"><i class="fa fa-eye"></i> @lang("messages.view")</a>
                    &nbsp;
                    @endcan
                    @can("user.delete")
                        <button data-href="{{action(\'App\Http\Controllers\ManageUserController@destroy\', [$id])}}" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_user_button"><i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")</button>
                    @endcan'
                )
                ->filterColumn('full_name', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(users.surname, ''), ' ', COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) like ?", ["%{$keyword}%"]);
                })
                ->filterColumn('business_name', function ($query, $keyword) {
                    $query->where('b.name', 'like', "%{$keyword}%");
                })
                ->filterColumn('role', function ($query, $keyword) {
                    // Allow searching the role column by
                    // pre_create_role for unassigned users.
                    $query->where('users.pre_create_role', 'like', "%{$keyword}%");
                })
                ->removeColumn('id')
                ->rawColumns(['action', 'username', 'role', 'business_name'])
                ->make(true);
        }

        // Count for each tab badge. Computed once on page load and
        // passed to the view so the tab labels can show live totals
        // (e.g. "Pre-created  4") without an extra AJAX roundtrip.
        $is_superadmin = auth()->user()->can('superadmin');
        $user_count_base = User::user()->where('is_cmmsn_agnt', 0);

        if ($is_superadmin) {
            $count_all       = (clone $user_count_base)->count();
            $count_assigned  = (clone $user_count_base)->whereNotNull('business_id')->count();
            $count_precreated = (clone $user_count_base)->whereNull('business_id')->count();
        } else {
            $count_all       = (clone $user_count_base)->where('business_id', request()->session()->get('user.business_id'))->count();
            $count_assigned  = $count_all;
            $count_precreated = 0;
        }

        return view('manage_user.index', compact('count_all', 'count_assigned', 'count_precreated'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! auth()->user()->can('user.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        //Check if subscribed or not, then check for users quota
        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse();
        } elseif (! $this->moduleUtil->isQuotaAvailable('users', $business_id)) {
            return $this->moduleUtil->quotaExpiredResponse('users', $business_id, action([\App\Http\Controllers\ManageUserController::class, 'index']));
        }

        $roles = $this->getRolesArray($business_id);
        $username_ext = $this->moduleUtil->getUsernameExtension();
        $locations = BusinessLocation::where('business_id', $business_id)
                                    ->Active()
                                    ->get();

        // The super admin can use the same form to "pre-create" a
        // user that will be assigned to a future business instead of
        // the current one. The flag is read by the view to show the
        // "Pre-create user" checkbox.
        $is_superadmin = auth()->user()->can('superadmin');

        //Get user form part from modules
        $form_partials = $this->moduleUtil->getModuleData('moduleViewPartials', ['view' => 'manage_user.create']);

        return view('manage_user.create')
                ->with(compact('roles', 'username_ext', 'locations', 'form_partials', 'is_superadmin'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('user.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            if (! empty($request->input('dob'))) {
                $request['dob'] = $this->moduleUtil->uf_date($request->input('dob'));
            }

            $request['cmmsn_percent'] = ! empty($request->input('cmmsn_percent')) ? $this->moduleUtil->num_uf($request->input('cmmsn_percent')) : 0;

            $request['max_sales_discount_percent'] = ! is_null($request->input('max_sales_discount_percent')) ? $this->moduleUtil->num_uf($request->input('max_sales_discount_percent')) : null;

            // Pre-create path (super admin only): the user is created
            // without a business and without a role, so they cannot
            // log in yet. They will be assigned to a new business
            // during the Superadmin -> Add Business flow.
            $is_pre_create = auth()->user()->can('superadmin')
                && $request->boolean('pre_create');

            if ($is_pre_create) {
                $user = $this->createPreCreatedUser($request);
            } else {
                $user = $this->moduleUtil->createUser($request);
            }

            event(new UserCreatedOrModified($user, 'added'));

            $output = ['success' => 1,
                'msg' => $is_pre_create
                    ? __('superadmin::lang.user_precreated_success')
                    : __('user.user_added'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect('users')->with('status', $output);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (! auth()->user()->can('user.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        // Super admin needs to view users from ANY business in the
        // chain (pre-created users have business_id = NULL; assigned
        // users belong to a store that may not be the super admin's
        // own). The previous `where('business_id', $business_id)`
        // scope caused 404s on every cross-business user and also
        // made the Essentials module crash downstream with a
        // "read property on null" error.
        if (auth()->user()->can('superadmin')) {
            $user = User::with(['contactAccess'])->findOrFail($id);
        } else {
            $user = User::where('business_id', $business_id)
                        ->with(['contactAccess'])
                        ->findOrFail($id);
        }

        //Get user view part from modules
        $view_partials = $this->moduleUtil->getModuleData('moduleViewPartials', ['view' => 'manage_user.show', 'user' => $user]);

        $users = User::forDropdown($business_id, false);

        $activities = Activity::forSubject($user)
           ->with(['causer', 'subject'])
           ->latest()
           ->get();

        return view('manage_user.show')->with(compact('user', 'view_partials', 'users', 'activities'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('user.update')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        // The role and location dropdowns on the edit form are
        // populated from the super admin's OWN business (i.e. the
        // current session's business) - not the target user's
        // business. This mirrors how the original /users module
        // works and is what the super admin expects when editing
        // users from other stores: a single shared pool of roles
        // and locations that gets applied uniformly.
        $user = auth()->user()->can('superadmin')
            ? User::with(['contactAccess'])->findOrFail($id)
            : User::where('business_id', $business_id)
                ->with(['contactAccess'])
                ->findOrFail($id);

        $roles = $this->getRolesArray($business_id);

        $contact_access = $user->contactAccess->pluck('name', 'id')->toArray();

        if ($user->status == 'active') {
            $is_checked_checkbox = true;
        } else {
            $is_checked_checkbox = false;
        }

        $locations = BusinessLocation::where('business_id', $business_id)
                                    ->get();

        $permitted_locations = $user->permitted_locations();
        $username_ext = $this->moduleUtil->getUsernameExtension();

        //Get user form part from modules
        $form_partials = $this->moduleUtil->getModuleData('moduleViewPartials', ['view' => 'manage_user.edit', 'user' => $user]);

        return view('manage_user.edit')
                ->with(compact('roles', 'user', 'contact_access', 'is_checked_checkbox', 'locations', 'permitted_locations', 'form_partials', 'username_ext'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //Disable in demo
        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }
        
        if (! auth()->user()->can('user.update')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

         //Check if subscribed
         if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse();
        }

        //Check for users quota if allow_login is true
        if (!empty($request->input('allow_login'))) {
            // Quota check uses the target user's business, not the
            // super admin's, otherwise the super admin could not
            // ever enable login on a user from another business.
            $quota_business_id = auth()->user()->can('superadmin')
                ? (User::where('id', $id)->value('business_id') ?: $business_id)
                : $business_id;
            if (! $this->moduleUtil->isQuotaAvailable('users', $quota_business_id)) {
                return $this->moduleUtil->quotaExpiredResponse('users', $quota_business_id, action([\App\Http\Controllers\ManageUserController::class, 'index']));
            }
        }

        try {
            $user_data = $request->only(['surname', 'first_name', 'last_name', 'email', 'selected_contacts', 'marital_status',
                'blood_group', 'contact_number', 'fb_link', 'twitter_link', 'social_media_1',
                'social_media_2', 'permanent_address', 'current_address',
                'guardian_name', 'custom_field_1', 'custom_field_2',
                'custom_field_3', 'custom_field_4', 'id_proof_name', 'id_proof_number', 'cmmsn_percent', 'gender', 'max_sales_discount_percent', 'family_number', 'alt_number', 'is_enable_service_staff_pin']);

            $user_data['status'] = ! empty($request->input('is_active')) ? 'active' : 'inactive';

            $user_data['is_enable_service_staff_pin'] = ! empty($request->input('is_enable_service_staff_pin')) ? true : false;

           

            if (! isset($user_data['selected_contacts'])) {
                $user_data['selected_contacts'] = 0;
            }

            if (empty($request->input('allow_login'))) {
                $user_data['username'] = null;
                $user_data['password'] = null;
                $user_data['allow_login'] = 0;
            } else {
                $user_data['allow_login'] = 1;
            }

            if (! empty($request->input('password'))) {
                $user_data['password'] = $user_data['allow_login'] == 1 ? Hash::make($request->input('password')) : null;
            }


            if (! empty($request->input('service_staff_pin'))) {
                $user_data['service_staff_pin'] = $request->input('service_staff_pin');
            }
            

            //Sales commission percentage
            $user_data['cmmsn_percent'] = ! empty($user_data['cmmsn_percent']) ? $this->moduleUtil->num_uf($user_data['cmmsn_percent']) : 0;

            $user_data['max_sales_discount_percent'] = ! is_null($user_data['max_sales_discount_percent']) ? $this->moduleUtil->num_uf($user_data['max_sales_discount_percent']) : null;

            if (! empty($request->input('dob'))) {
                $user_data['dob'] = $this->moduleUtil->uf_date($request->input('dob'));
            }

            if (! empty($request->input('bank_details'))) {
                $user_data['bank_details'] = json_encode($request->input('bank_details'));
            }

            DB::beginTransaction();

            if ($user_data['allow_login'] && $request->has('username')) {
                $user_data['username'] = $request->input('username');
                $ref_count = $this->moduleUtil->setAndGetReferenceCount('username');
                if (blank($user_data['username'])) {
                    $user_data['username'] = $this->moduleUtil->generateReferenceNumber('username', $ref_count);
                }

                $username_ext = $this->moduleUtil->getUsernameExtension();
                if (! empty($username_ext)) {
                    $user_data['username'] .= $username_ext;
                }
            }

            $user = auth()->user()->can('superadmin')
                ? User::findOrFail($id)
                : User::where('business_id', $business_id)->findOrFail($id);

            $user->update($user_data);
            $role_id = $request->input('role');
            $user_role = $user->roles->first();
            $previous_role = ! empty($user_role->id) ? $user_role->id : 0;
            if ($previous_role != $role_id) {
                $is_admin = $this->moduleUtil->is_admin($user);
                $all_admins = $this->getAdmins();
                //If only one admin then can not change role
                if ($is_admin && count($all_admins) <= 1) {
                    throw new \Exception(__('lang_v1.cannot_change_role'));
                }
                if (! empty($previous_role)) {
                    $user->removeRole($user_role->name);
                }

                $role = Role::findOrFail($role_id);
                $user->assignRole($role->name);
            }

            //Grant Location permissions
            $this->moduleUtil->giveLocationPermissions($user, $request);

            //Assign selected contacts
            if ($user_data['selected_contacts'] == 1) {
                $contact_ids = $request->get('selected_contact_ids');
            } else {
                $contact_ids = [];
            }
            $user->contactAccess()->sync($contact_ids);

            //Update module fields for user
            $this->moduleUtil->getModuleData('afterModelSaved', ['event' => 'user_saved', 'model_instance' => $user]);

            $this->moduleUtil->activityLog($user, 'edited', null, ['name' => $user->user_full_name]);
           
            event(new UserCreatedOrModified($user, 'updated'));
            
            $output = ['success' => 1,
                'msg' => __('user.user_update_success'),
            ];

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => $e->getMessage(),
            ];
        }

        return redirect('users')->with('status', $output);
    }

    private function getAdmins()
    {
        $business_id = request()->session()->get('user.business_id');
        $admins = User::role('Admin#'.$business_id)->get();

        return $admins;
    }

    /**
     * Create a "pre-created" (unassigned) user from the standard
     * User Management form. The super admin toggles the "pre_create"
     * checkbox when they want to set up credentials ahead of creating
     * a new business, so the new business can pick this user as its
     * owner instead of creating a brand-new owner at business-create
     * time.
     *
     * The user is created with:
     *   - business_id = NULL  (no business attached yet)
     *   - allow_login = 0     (cannot log in until assigned)
     *   - status      = 'active' (kept valid for future assignment)
     * No role is assigned yet — the business-creation flow grants
     * `Admin#<business_id>` automatically when the user is attached.
     */
    private function createPreCreatedUser(Request $request)
    {
        $data = $request->only([
            'surname', 'first_name', 'last_name', 'username', 'email',
            'password', 'contact_number', 'role',
        ]);

        // Note: the User model uses SoftDeletes, so we explicitly
        // exclude soft-deleted rows from the unique checks. Without
        // this, a username / email that was once used and then the
        // user was soft-deleted can never be re-used, which causes
        // the "Something went wrong" alert in the browser (the form's
        // catch-all swallows the ValidationException).
        $request->validate([
            'surname'    => 'nullable|string|max:191',
            'first_name' => 'required|string|max:191',
            'last_name'  => 'nullable|string|max:191',
            'username'   => [
                'required',
                'string',
                'max:191',
                Rule::unique('users', 'username')->whereNull('deleted_at'),
            ],
            'email'      => [
                'required',
                'email',
                'max:191',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'password'   => 'required|string|min:5',
            'role'       => 'nullable|string|max:191',
        ]);

        // Resolve the role NAME (without the #<business_id> suffix).
        // The super admin picks a role on the existing form (e.g.
        // "Cashier", "Admin"). The submitted value is the Spatie
        // role id, so we look it up, extract the human-readable
        // name, and strip the suffix. This name is what the
        // business-creation flow will look up against the new
        // business's roles (e.g. "Cashier#5").
        $pre_create_role_name = null;
        if (! empty($data['role'])) {
            $selected_role = \Spatie\Permission\Models\Role::find($data['role']);
            if ($selected_role) {
                // Strip "#<digits>" suffix if present (e.g. "Cashier#1" -> "Cashier")
                $pre_create_role_name = preg_replace('/#\d+$/', '', $selected_role->name);
            }
        }

        $user = User::create([
            'surname'         => $data['surname'] ?? null,
            'first_name'      => $data['first_name'],
            'last_name'       => $data['last_name'] ?? null,
            'username'        => $data['username'],
            'email'           => $data['email'],
            'password'        => Hash::make($data['password']),
            'language'        => env('APP_LOCALE', 'en'),
            'user_type'       => 'user',
            'business_id'     => null,
            'allow_login'     => 0,
            'pre_create_role' => $pre_create_role_name,
            'status'          => 'active',
        ]);

        return $user;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //Disable in demo
        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        if (! auth()->user()->can('user.delete')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');

                // Super admin can delete any user; non-superadmin
                // is still restricted to their own business.
                $user = auth()->user()->can('superadmin')
                    ? User::findOrFail($id)
                    : User::where('business_id', $business_id)->findOrFail($id);

                $this->moduleUtil->activityLog($user, 'deleted', null, ['name' => $user->user_full_name, 'id' => $user->id]);

                $user->delete();
                event(new UserCreatedOrModified($user, 'deleted'));

                $output = ['success' => true,
                    'msg' => __('user.user_delete_success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Retrives roles array (Hides admin role from non admin users)
     *
     * @param  int  $business_id
     * @return array $roles
     */
    private function getRolesArray($business_id)
    {
        $roles_array = Role::where('business_id', $business_id)->get()->pluck('name', 'id');
        $roles = [];

        $is_admin = $this->moduleUtil->is_admin(auth()->user(), $business_id);

        foreach ($roles_array as $key => $value) {
            if (! $is_admin && $value == 'Admin#'.$business_id) {
                continue;
            }
            $roles[$key] = str_replace('#'.$business_id, '', $value);
        }

        return $roles;
    }

    /**
     * Signes in from user id
     *
     * @param  int  $id
     */
    public function signInAsUser($id)
    {
        if (! auth()->user()->can('superadmin') && empty(session('previous_user_id'))) {
            abort(403, 'Unauthorized action.');
        }

        $user_id = auth()->user()->id;
        $username = auth()->user()->username;
        session()->flush();

        if (request()->has('save_current')) {
            session(['previous_user_id' => $user_id, 'previous_username' => $username]);
        }

        Auth::loginUsingId($id);

        return redirect()->route('home');
    }
}
