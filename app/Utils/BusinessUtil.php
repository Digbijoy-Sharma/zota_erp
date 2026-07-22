<?php

namespace App\Utils;

use App\Barcode;
use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\Currency;
use App\InvoiceLayout;
use App\InvoiceScheme;
use App\NotificationTemplate;
use App\Printer;
use App\Unit;
use App\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\VariationLocationDetails;


class BusinessUtil extends Util
{
    /**
     * Adds a default settings/resources for a new business
     *
     * @param  int  $business_id
     * @param  int  $user_id
     * @return bool
     */
    public function newBusinessDefaultResources($business_id, $user_id)
    {
        $user = User::find($user_id);

        // Roles are a chain-wide catalogue. Instead of only
        // creating the hard-coded `Admin` and `Cashier` roles for
        // every new business, we clone every role that the super
        // admin has defined in their own business (business #1)
        // into the new business with the same name pattern
        // (`<RoleName>#<new_business_id>`) and the same permission
        // set. This is what makes a pre-created user with
        // pre_create_role = "Pharmacy" actually get the
        // "Pharmacy" role on assignment instead of silently
        // falling back to Admin.
        $admin_role = $this->cloneRolesFromTemplateBusiness($business_id);

        // Assign the freshly-cloned Admin role to the owner so
        // the new business is operable before any pre_create_role
        // override kicks in.
        $user->assignRole($admin_role->name);

        $business = Business::findOrFail($business_id);

        // Walk-In Customer is a single, chain-wide (global) record
        // shared by every business. We do NOT create a new row per
        // business - that produced dozens of duplicate "Walk-In
        // Customer" entries in the customer list, one per store, all
        // stamped with the same contact_id (CO0001). The master
        // record was created when the very first business was set up
        // and is reused here. The contact reference counter is still
        // bumped so that user-created customers in this business get
        // the next available code, but the walk-in code (CO0001) is
        // never reissued.
        $this->setAndGetReferenceCount('contacts', $business_id);

        //create default invoice setting for new business
        InvoiceScheme::create(['name' => 'Default',
            'scheme_type' => 'blank',
            'prefix' => '',
            'start_number' => 1,
            'total_digits' => 4,
            'is_default' => 1,
            'business_id' => $business_id,
        ]);
        //create default invoice layour for new business
        InvoiceLayout::create(['name' => 'Default',
            'header_text' => null,
            'invoice_no_prefix' => 'Invoice No.',
            'invoice_heading' => 'Invoice',
            'sub_total_label' => 'Subtotal',
            'discount_label' => 'Discount',
            'tax_label' => 'Tax',
            'total_label' => 'Total',
            'show_landmark' => 1,
            'show_city' => 1,
            'show_state' => 1,
            'show_zip_code' => 1,
            'show_country' => 1,
            'highlight_color' => '#000000',
            'footer_text' => '',
            'is_default' => 1,
            'business_id' => $business_id,
            'invoice_heading_not_paid' => '',
            'invoice_heading_paid' => '',
            'total_due_label' => 'Total Due',
            'paid_label' => 'Total Paid',
            'show_payments' => 1,
            'show_customer' => 1,
            'customer_label' => 'Customer',
            'table_product_label' => 'Product',
            'table_qty_label' => 'Quantity',
            'table_unit_price_label' => 'Unit Price',
            'table_subtotal_label' => 'Subtotal',
            'date_label' => 'Date',
        ]);

        //create default barcode setting for new business
        // Barcode::create(['name' => 'Default',
        //                 'description' => '',
        //                 'width' => 37.29,
        //                 'height' => 25.93,
        //                 'top_margin' => 5,
        //                 'left_margin' => 5,
        //                 'row_distance' => 1,
        //                 'col_distance' => 1,
        //                 'stickers_in_one_row' => 4,
        //                 'is_default' => 1,
        //                 'business_id' => $business_id
        //             ]);

        //Add Default Unit for new business
        $unit = [
            'business_id' => $business_id,
            'actual_name' => 'Pieces',
            'short_name' => 'Pc(s)',
            'allow_decimal' => 0,
            'created_by' => $user_id,
        ];
        Unit::create($unit);

        //Create default notification templates
        $notification_templates = NotificationTemplate::defaultNotificationTemplates($business_id);
        foreach ($notification_templates as $notification_template) {
            NotificationTemplate::create($notification_template);
        }

        return true;
    }

    /**
     * Clone every role that the super admin defined in their own
     * business into a freshly-created business. The super admin's
     * business is the lowest `id` in the `business` table (the very
     * first one, where the owner logs in as `admin`); that
     * business is treated as the role template for the entire
     * chain.
     *
     * For each template role we create a brand-new `Role` row
     * named `<RoleName>#<new_business_id>`, attach the same
     * permission set, and return the cloned Admin role so the
     * caller can assign it to the new business's owner.
     *
     * @param  int  $business_id  the brand-new business id
     * @return \Spatie\Permission\Models\Role  the cloned Admin role
     */
    public function cloneRolesFromTemplateBusiness($business_id)
    {
        $template_business = Business::orderBy('id')->first();

        if (! $template_business) {
            // No template business exists yet (should not happen
            // since we are creating a new business, which means at
            // least the super admin's business is in the table).
            // Fall back to the original hard-coded Admin role.
            return Role::create(['name' => 'Admin#'.$business_id,
                'business_id' => $business_id,
                'guard_name' => 'web', 'is_default' => 1,
            ]);
        }

        $template_roles = Role::where('business_id', $template_business->id)->get();
        $cloned_admin = null;

        foreach ($template_roles as $template) {
            // Strip the template business suffix and re-attach
            // the new business suffix so the role name follows
            // the existing pattern: `RoleName#<biz>`.
            $base = preg_replace('/#\d+$/', '', $template->name);
            $new_name = $base . '#' . $business_id;

            $cloned = Role::create([
                'name'       => $new_name,
                'business_id' => $business_id,
                'guard_name' => 'web',
                'is_default' => $template->is_default,
            ]);

            // Carry over the permission set so the role is
            // functional out of the box (Pharmacy, Cashier, etc.
            // need their permissions to do anything meaningful).
            $permission_names = $template->permissions->pluck('name')->all();
            if (! empty($permission_names)) {
                $cloned->syncPermissions($permission_names);
            }

            if ($base === 'Admin') {
                $cloned_admin = $cloned;
            }
        }

        // Final safety net: if the template business has no
        // `Admin` role, create one now so the rest of the system
        // can still find `Admin#<biz>`.
        if (! $cloned_admin) {
            $cloned_admin = Role::create(['name' => 'Admin#'.$business_id,
                'business_id' => $business_id,
                'guard_name' => 'web', 'is_default' => 1,
            ]);
        }

        return $cloned_admin;
    }

    /**
     * On-demand clone of a single role by base name (e.g.
     * "Pharmacy") from the template business into the given
     * business. Returns the newly-cloned role, or null if no
     * matching template role exists.
     *
     * Used by `BusinessController::store()` as a defensive
     * fallback when a pre-created user with a pre_create_role is
     * being assigned to a business that does not have that role
     * yet (e.g. the business was created before role cloning was
     * introduced). Without this fallback, the role assignment
     * silently fails and the user ends up with the default
     * Admin#<biz> role.
     *
     * @param  int     $business_id
     * @param  string  $base_name   the role base name, e.g. "Pharmacy"
     * @return \Spatie\Permission\Models\Role|null
     */
    public function cloneRoleFromTemplateByName($business_id, $base_name)
    {
        $template_business = Business::orderBy('id')->first();
        if (! $template_business) {
            return null;
        }

        $template = Role::where('business_id', $template_business->id)
            ->where('name', 'LIKE', preg_replace('/[#%_]/', '\\$0', $base_name) . '#%')
            ->first();

        if (! $template) {
            return null;
        }

        $new_name = $base_name . '#' . $business_id;
        $cloned = Role::create([
            'name'        => $new_name,
            'business_id' => $business_id,
            'guard_name'  => 'web',
            'is_default'  => $template->is_default,
        ]);

        $permission_names = $template->permissions->pluck('name')->all();
        if (! empty($permission_names)) {
            $cloned->syncPermissions($permission_names);
        }

        return $cloned;
    }

    /**
     * Propagate a role's current permission set to every other
     * business that has a role with the same base name.
     *
     * The "template" role lives in the first business (typically the
     * super admin's own business). When that role is edited via
     * RoleController::update(), we want the changes to flow out to
     * every business that already has a copy of the same role
     * (e.g. "Pharmacist#1" -> "Pharmacist#2", "Pharmacist#3", ...).
     *
     * Idempotent: if no other business carries the role, this is a
     * no-op. New businesses that get the role assigned later will
     * still pick up the latest permissions via
     * cloneRoleFromTemplateByName().
     *
     * @param  \Spatie\Permission\Models\Role  $role  the freshly-updated template role
     * @return int  number of tenant roles that were synced
     */
    public function syncRolePermissionsToAllBusinesses($role)
    {
        if (! $role) {
            return 0;
        }

        // Only sync from the template business (first business).
        $template_business = Business::orderBy('id')->first();
        if (! $template_business || (int) $role->business_id !== (int) $template_business->id) {
            return 0;
        }

        // Strip the "#<id>" suffix to derive the base name.
        $base_name = preg_replace('/#\d+$/', '', $role->name);
        if ($base_name === '' || $base_name === $role->name) {
            return 0;
        }

        $permission_names = $role->permissions->pluck('name')->all();

        $synced = 0;
        $tenant_roles = Role::where('name', 'LIKE', preg_replace('/[#%_]/', '\\$0', $base_name) . '#%')
            ->where('id', '!=', $role->id)
            ->get();

        foreach ($tenant_roles as $tenant_role) {
            try {
                if (! empty($permission_names)) {
                    $tenant_role->syncPermissions($permission_names);
                } else {
                    $tenant_role->syncPermissions([]);
                }
                $synced++;
            } catch (\Throwable $e) {
                \Log::warning('Role sync failed for ' . $tenant_role->name . ': ' . $e->getMessage());
            }
        }

        return $synced;
    }

    /**
     * Gives a list of all currencies
     *
     * @return array
     */
    public function allCurrencies()
    {
        $currencies = Currency::select('id', DB::raw("concat(country, ' - ',currency, '(', code, ') ') as info"))
                ->orderBy('country')
                ->pluck('info', 'id');

        return $currencies;
    }

    /**
     * Gives a list of all timezone
     *
     * @return array
     */
    public function allTimeZones()
    {
        $datetime = new \DateTimeZone('EDT');

        $timezones = $datetime->listIdentifiers();
        $timezone_list = [];
        foreach ($timezones as $timezone) {
            $timezone_list[$timezone] = $timezone;
        }

        return $timezone_list;
    }

    /**
     * Gives a list of all accouting methods
     *
     * @return array
     */
    public function allAccountingMethods()
    {
        return [
            'fifo' => __('business.fifo'),
            'lifo' => __('business.lifo'),
        ];
    }

    /**
     * Creates new business with default settings.
     *
     * @return array
     */
    public function createNewBusiness($business_details)
    {
        $business_details['sell_price_tax'] = 'includes';

        $business_details['default_profit_percent'] = 25;

        //Add POS shortcuts
        $business_details['keyboard_shortcuts'] = '{"pos":{"express_checkout":"shift+e","pay_n_ckeckout":"shift+p","draft":"shift+d","cancel":"shift+c","edit_discount":"shift+i","edit_order_tax":"shift+t","add_payment_row":"shift+r","finalize_payment":"shift+f","recent_product_quantity":"f2","add_new_product":"f4"}}';

        //Add prefixes
        $business_details['ref_no_prefixes'] = [
            'purchase' => 'PO',
            'stock_transfer' => 'ST',
            'stock_adjustment' => 'SA',
            'sell_return' => 'CN',
            'expense' => 'EP',
            'contacts' => 'CO',
            'purchase_payment' => 'PP',
            'sell_payment' => 'SP',
            'business_location' => 'BL',
        ];

        //Disable inline tax editing
        $business_details['enable_inline_tax'] = 0;

        // Multi-unit selling/purchasing (base unit + sub-units, e.g.
        // Tablet / Strip / Baby Box) is central to this chain's
        // workflow, and master products synced into a new store carry
        // sub-unit configuration. Enable it from day one so the
        // product forms show the sub-unit fields without manual
        // per-store setup.
        $business_details['enable_sub_units'] = $business_details['enable_sub_units'] ?? 1;

        // Purchase Orders are part of every store's procurement workflow in
        // this chain, so enable the feature by default for new stores. The
        // flag lives inside the common_settings JSON; merge rather than
        // overwrite so any settings passed by the caller are preserved.
        $common_settings = $business_details['common_settings'] ?? [];
        if (! isset($common_settings['enable_purchase_order'])) {
            $common_settings['enable_purchase_order'] = 1;
        }
        $business_details['common_settings'] = $common_settings;

        $business = Business::create_business($business_details);

        return $business;
    }

    /**
     * Gives details for a business
     *
     * @return object
     */
    public function getDetails($business_id)
    {
        $details = Business::leftjoin('tax_rates AS TR', 'business.default_sales_tax', 'TR.id')
                        ->leftjoin('currencies AS cur', 'business.currency_id', 'cur.id')
                        ->select(
                            'business.*',
                            'cur.code as currency_code',
                            'cur.symbol as currency_symbol',
                            'thousand_separator',
                            'decimal_separator',
                            'TR.amount AS tax_calculation_amount',
                            'business.default_sales_discount'
                        )
                        ->where('business.id', $business_id)
                        ->first();

        return $details;
    }

    /**
     * Gives current financial year
     *
     * @return array
     */
    public function getCurrentFinancialYear($business_id)
    {
        $business = Business::where('id', $business_id)->first();
        $start_month = $business->fy_start_month;
        $end_month = $start_month - 1;
        if ($start_month == 1) {
            $end_month = 12;
        }

        $start_year = date('Y');
        //if current month is less than start month change start year to last year
        if (date('n') < $start_month) {
            $start_year = $start_year - 1;
        }

        $end_year = date('Y');
        //if current month is greater than end month change end year to next year
        if (date('n') > $end_month) {
            $end_year = $start_year + 1;
        }
        $start_date = $start_year.'-'.str_pad($start_month, 2, 0, STR_PAD_LEFT).'-01';
        $end_date = $end_year.'-'.str_pad($end_month, 2, 0, STR_PAD_LEFT).'-01';
        $end_date = date('Y-m-t', strtotime($end_date));

        $output = [
            'start' => $start_date,
            'end' => $end_date,
        ];

        return $output;
    }

    /**
     * Adds a new location to a business
     *
     * @param  int  $business_id
     * @param  array  $location_details
     * @param  int  $invoice_layout_id default null
     * @return location object
     */
    public function addLocation($business_id, $location_details, $invoice_scheme_id = null, $invoice_layout_id = null)
    {
        if (empty($invoice_scheme_id)) {
            $layout = InvoiceLayout::where('is_default', 1)
                                    ->where('business_id', $business_id)
                                    ->first();
            $invoice_layout_id = $layout->id;
        }

        if (empty($invoice_scheme_id)) {
            $scheme = InvoiceScheme::where('is_default', 1)
                                    ->where('business_id', $business_id)
                                    ->first();
            $invoice_scheme_id = $scheme->id;
        }

        //Update reference count
        $ref_count = $this->setAndGetReferenceCount('business_location', $business_id);
        $location_id = $this->generateReferenceNumber('business_location', $ref_count, $business_id);

        //Enable all payment methods by default
        $payment_types = $this->payment_types();
        $location_payment_types = [];
        foreach ($payment_types as $key => $value) {
            $location_payment_types[$key] = [
                'is_enabled' => 1,
                'account' => null,
            ];
        }
        $location = BusinessLocation::create(['business_id' => $business_id,
            'name' => $location_details['name'],
            'landmark' => $location_details['landmark'],
            'city' => $location_details['city'],
            'state' => $location_details['state'],
            'zip_code' => $location_details['zip_code'],
            'country' => $location_details['country'],
            'invoice_scheme_id' => $invoice_scheme_id,
            'invoice_layout_id' => $invoice_layout_id,
            'sale_invoice_layout_id' => $invoice_layout_id,
            'mobile' => ! empty($location_details['mobile']) ? $location_details['mobile'] : '',
            'alternate_number' => ! empty($location_details['alternate_number']) ? $location_details['alternate_number'] : '',
            'website' => ! empty($location_details['website']) ? $location_details['website'] : '',
            'email' => '',
            'location_id' => $location_id,
            'default_payment_accounts' => json_encode($location_payment_types),
        ]);

        return $location;
    }

    /**
     * Return the invoice layout details
     *
     * @param  int  $business_id
     * @param  array  $layout_id = null
     * @return location object
     */
    public function invoiceLayout($business_id, $layout_id = null)
    {
        $layout = null;
        if (! empty($layout_id)) {
            $layout = InvoiceLayout::find($layout_id);
        }

        //If layout is not found (deleted) then get the default layout for the business
        if (empty($layout)) {
            $layout = InvoiceLayout::where('business_id', $business_id)
                        ->where('is_default', 1)
                        ->first();
        }
        //$output = []
        return $layout;
    }

    /**
     * Return the printer configuration
     *
     * @param  int  $business_id
     * @param  int  $printer_id
     * @return array
     */
    public function printerConfig($business_id, $printer_id)
    {
        $printer = Printer::where('business_id', $business_id)
                    ->find($printer_id);

        $output = [];

        if (! empty($printer)) {
            $output['connection_type'] = $printer->connection_type;
            $output['capability_profile'] = $printer->capability_profile;
            $output['char_per_line'] = $printer->char_per_line;
            $output['ip_address'] = $printer->ip_address;
            $output['port'] = $printer->port;
            $output['path'] = $printer->path;
            $output['server_url'] = $printer->server_url;
        }

        return $output;
    }

    /**
     * Return the date range for which editing of transaction for a business is allowed.
     *
     * @param  int  $business_id
     * @param  char  $edit_transaction_period
     * @return array
     */
    public function editTransactionDateRange($business_id, $edit_transaction_period)
    {
        if (is_numeric($edit_transaction_period)) {
            return ['start' => \Carbon::today()
                ->subDays($edit_transaction_period),
                'end' => \Carbon::today(),
            ];
        } elseif ($edit_transaction_period == 'fy') {
            //Editing allowed for current financial year
            return $this->getCurrentFinancialYear($business_id);
        }

        return false;
    }

    /**
     * Return the default setting for the pos screen.
     *
     * @return array
     */
    public function defaultPosSettings()
    {
        return ['disable_pay_checkout' => 0, 'disable_draft' => 0, 'disable_express_checkout' => 0, 'hide_product_suggestion' => 0, 'hide_recent_trans' => 0, 'disable_discount' => 0, 'disable_order_tax' => 0, 'is_pos_subtotal_editable' => 0];
    }

    /**
     * Return the default setting for the email.
     *
     * @return array
     */
    public function defaultEmailSettings()
    {
        return ['mail_host' => '', 'mail_port' => '', 'mail_username' => '', 'mail_password' => '', 'mail_encryption' => '', 'mail_from_address' => '', 'mail_from_name' => ''];
    }

    /**
     * Return the default setting for the email.
     *
     * @return array
     */
    public function defaultSmsSettings()
    {
        return ['url' => '', 'send_to_param_name' => 'to', 'msg_param_name' => 'text', 'request_method' => 'post', 'param_1' => '', 'param_val_1' => '', 'param_2' => '', 'param_val_2' => '', 'param_3' => '', 'param_val_3' => '', 'param_4' => '', 'param_val_4' => '', 'param_5' => '', 'param_val_5' => '', 'data_parameter_type' => 'form-data'];
    }

}
