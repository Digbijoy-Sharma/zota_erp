<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Business;
use App\Contact;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Superadmin management of supplier (warehouse) portal logins.
 *
 * Each login is a real App\User with user_type = 'user_supplier',
 * business_id = template (superadmin) business, and common_supplier_id
 * pointing at a MASTER supplier Contact. The user is granted the
 * 'Supplier#<template>' role (seeded in P4). Once created, the supplier
 * logs in through the normal /login and is routed to the supplier portal.
 */
class SupplierLoginController extends Controller
{
    /**
     * List master suppliers with their portal-login status.
     */
    public function index(Request $request)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $super_admin_business_id = $request->session()->get('user.business_id');

        $suppliers = Contact::where('business_id', $super_admin_business_id)
            ->whereIn('type', ['supplier', 'both'])
            ->orderBy('name')
            ->get();

        // Existing supplier logins keyed by master supplier id.
        $logins = User::where('user_type', 'user_supplier')
            ->whereNotNull('common_supplier_id')
            ->get()
            ->keyBy('common_supplier_id');

        return view('superadmin::supplier_logins.index', compact('suppliers', 'logins'));
    }

    /**
     * Create a portal login for a master supplier.
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'contact_id' => 'required|integer|exists:contacts,id',
            // Ignore soft-deleted users (matches the codebase's app-layer
            // uniqueness scheme after unique DB constraints were dropped).
            'username' => ['required', 'string', 'max:191', Rule::unique('users', 'username')->whereNull('deleted_at')],
            'email' => 'nullable|email|max:191',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $super_admin_business_id = $request->session()->get('user.business_id');
        $template_business_id = Business::orderBy('id')->value('id');

        // The supplier must be a master supplier in the superadmin business.
        $contact = Contact::where('business_id', $super_admin_business_id)
            ->whereIn('type', ['supplier', 'both'])
            ->findOrFail($request->input('contact_id'));

        // One login per master supplier.
        $existing = User::where('user_type', 'user_supplier')
            ->where('common_supplier_id', $contact->id)
            ->exists();
        if ($existing) {
            return redirect()->back()->with('status', [
                'success' => 0,
                'msg' => __('superadmin::lang.supplier_login_exists'),
            ]);
        }

        try {
            DB::beginTransaction();

            $user = new User();
            $user->surname = '';
            $user->first_name = $contact->name ?: ('Supplier #'.$contact->id);
            $user->last_name = $contact->supplier_business_name ?: '';
            $user->username = $request->input('username');
            $user->email = $request->input('email');
            $user->password = Hash::make($request->input('password'));
            $user->language = 'en';
            $user->business_id = $template_business_id;
            $user->user_type = 'user_supplier';
            $user->common_supplier_id = $contact->id;
            $user->allow_login = 1;
            $user->status = 'active';
            $user->save();

            // Assign the Supplier role. Resolve/create it defensively so a
            // missing (unseeded) or template-id-shifted role does not fail
            // login creation with an opaque error.
            $role = $this->resolveSupplierRole($template_business_id);
            $user->assignRole($role->name);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return redirect()->back()->with('status', [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ]);
        }

        return redirect()->back()->with('status', [
            'success' => 1,
            'msg' => __('superadmin::lang.supplier_login_created', ['supplier' => $contact->name]),
        ]);
    }

    /**
     * Resolve (or create) the Supplier role for the template business and
     * ensure it carries the supplier-portal permissions. Defensive so login
     * creation works even if the P4 seed was skipped or the template id
     * shifted.
     */
    protected function resolveSupplierRole($template_business_id)
    {
        $role = Role::firstOrCreate(
            ['name' => 'Supplier#'.$template_business_id],
            ['business_id' => $template_business_id, 'guard_name' => 'web', 'is_default' => 0]
        );

        $perms = ['supplier_portal.view_dashboard', 'supplier_portal.view_po', 'supplier_portal.update_po_status'];
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $role->givePermissionTo($perms);

        return $role;
    }

    /**
     * Enable / disable an existing supplier login.
     */
    public function toggle(Request $request, $user_id)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $user = User::where('user_type', 'user_supplier')->findOrFail($user_id);
        $user->allow_login = $user->allow_login ? 0 : 1;
        $user->status = $user->allow_login ? 'active' : 'inactive';
        $user->save();

        return redirect()->back()->with('status', [
            'success' => 1,
            'msg' => $user->allow_login
                ? __('superadmin::lang.supplier_login_enabled')
                : __('superadmin::lang.supplier_login_disabled'),
        ]);
    }

    /**
     * Reset an existing supplier login's password.
     */
    public function resetPassword(Request $request, $user_id)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::where('user_type', 'user_supplier')->findOrFail($user_id);
        $user->password = Hash::make($request->input('password'));
        $user->save();

        return redirect()->back()->with('status', [
            'success' => 1,
            'msg' => __('superadmin::lang.supplier_login_password_reset'),
        ]);
    }
}
