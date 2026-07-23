<?php

use App\Business;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role-management integration for the supplier (warehouse) portal:
 *  - Seeds the three supplier-portal permissions (guard "web") so they
 *    exist chain-wide before any role edit (they also auto-materialize
 *    on role save via RoleController::__createPermissionIfNotExists,
 *    but the base role below needs them to exist now).
 *  - Creates a base "Supplier" role in the template (superadmin) business
 *    with those permissions. Supplier login users (P5) are assigned this
 *    role. BusinessUtil::cloneRolesFromTemplateBusiness will carry it to
 *    future stores automatically.
 */
return new class extends Migration
{
    protected $permissions = [
        'supplier_portal.view_dashboard',
        'supplier_portal.view_po',
        'supplier_portal.update_po_status',
    ];

    public function up()
    {
        foreach ($this->permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $template_business_id = Business::orderBy('id')->value('id');
        if (! empty($template_business_id)) {
            $role = Role::firstOrCreate(
                ['name' => 'Supplier#'.$template_business_id],
                [
                    'business_id' => $template_business_id,
                    'guard_name' => 'web',
                    'is_default' => 0,
                ]
            );

            $role->givePermissionTo($this->permissions);
        }
    }

    public function down()
    {
        $template_business_id = Business::orderBy('id')->value('id');
        if (! empty($template_business_id)) {
            $role = Role::where('name', 'Supplier#'.$template_business_id)->first();
            if ($role) {
                $role->delete();
            }
        }

        Permission::whereIn('name', $this->permissions)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
