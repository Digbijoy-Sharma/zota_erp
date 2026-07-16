<?php

namespace App;

use DB;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Contact extends Authenticatable
{
    use Notifiable;
    use SoftDeletes;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'shipping_custom_field_details' => 'array',
    ];

    /**
     * Get the business that owns the user.
     */
    public function business()
    {
        return $this->belongsTo(\App\Business::class);
    }

    public function scopeActive($query)
    {
        return $query->where('contacts.contact_status', 'active');
    }

    /**
     * Filters only own created suppliers or has access to the supplier
     */
    public function scopeOnlySuppliers($query)
    {
        if (auth()->check() && ! auth()->user()->can('supplier.view') && ! auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $query->whereIn('contacts.type', ['supplier', 'both']);

        if (auth()->check() && ! auth()->user()->can('supplier.view') && auth()->user()->can('supplier.view_own')) {
            $query->leftjoin('user_contact_access AS ucas', 'contacts.id', 'ucas.contact_id');
            $query->where(function ($q) {
                $user_id = auth()->user()->id;
                $q->where('contacts.created_by', $user_id)
                    ->orWhere('ucas.user_id', $user_id);
            });
        }

        return $query;
    }

    /**
     * Filters only own created customers or has access to the customer
     */
    public function scopeOnlyCustomers($query)
    {
        //Commented because of issue in woocommerce sync
        // if (auth()->check() && !auth()->user()->can('customer.view') && !auth()->user()->can('customer.view_own')) {
        //     abort(403, 'Unauthorized action.');
        // }

        $query->whereIn('contacts.type', ['customer', 'both']);

        if (auth()->check() && ! auth()->user()->can('customer.view') && auth()->user()->can('customer.view_own')) {
            $query->leftjoin('user_contact_access AS ucas', 'contacts.id', 'ucas.contact_id');
            $query->where(function ($q) {
                $user_id = auth()->user()->id;
                $q->where('contacts.created_by', $user_id)
                    ->orWhere('ucas.user_id', $user_id);
            });
        }

        return $query;
    }

    /**
     * Filters only own created contact or has access to the contact
     */
    public function scopeOnlyOwnContact($query)
    {
        $query->leftjoin('user_contact_access AS ucas', 'contacts.id', 'ucas.contact_id');
        $query->where(function ($q) {
            $user_id = auth()->user()->id;
            $q->where('contacts.created_by', $user_id)
                ->orWhere('ucas.user_id', $user_id);
        });

        return $query;
    }

    /**
     * Get all of the contacts's notes & documents.
     */
    public function documentsAndnote()
    {
        return $this->morphMany(\App\DocumentAndNote::class, 'notable');
    }

    /**
     * Return list of contact dropdown for a business
     *
     * @param $business_id int
     * @param $exclude_default = false (boolean)
     * @param $prepend_none = true (boolean)
     * @return array users
     */
    public static function contactDropdown($business_id, $exclude_default = false, $prepend_none = true, $append_id = true)
    {
        $query = Contact::where('business_id', $business_id)
                    ->where('type', '!=', 'lead')
                    ->active();

        if ($exclude_default) {
            $query->where('is_default', 0);
        }

        if ($append_id) {
            $query->select(
                DB::raw("IF(contacts.contact_id IS NULL OR contacts.contact_id='', name, CONCAT(name, ' - ', COALESCE(supplier_business_name, ''), '(', contacts.contact_id, ')')) AS supplier"),
                'contacts.id'
                    );
        } else {
            $query->select(
                'contacts.id',
                DB::raw("IF (supplier_business_name IS not null, CONCAT(name, ' (', supplier_business_name, ')'), name) as supplier")
            );
        }

        if (auth()->check() && ! auth()->user()->can('supplier.view') && auth()->user()->can('supplier.view_own')) {
            $query->leftjoin('user_contact_access AS ucas', 'contacts.id', 'ucas.contact_id');
            $query->where(function ($q) {
                $user_id = auth()->user()->id;
                $q->where('contacts.created_by', $user_id)
                    ->orWhere('ucas.user_id', $user_id);
            });
        }

        $contacts = $query->pluck('supplier', 'contacts.id');

        //Prepend none
        if ($prepend_none) {
            $contacts = $contacts->prepend(__('lang_v1.none'), '');
        }

        return $contacts;
    }

    /**
     * Return list of suppliers dropdown for a business
     *
     * @param $business_id int
     * @param $prepend_none = true (boolean)
     * @return array users
     */
    public static function suppliersDropdown($business_id, $prepend_none = true, $append_id = true)
    {
        $all_contacts = Contact::where('contacts.business_id', $business_id)
                        ->whereIn('contacts.type', ['supplier', 'both'])
                        ->active();

        if ($append_id) {
            $all_contacts->select(
                DB::raw("IF(contacts.contact_id IS NULL OR contacts.contact_id='', name, CONCAT(contacts.name, ' - ', COALESCE(contacts.supplier_business_name, ''), '(', contacts.contact_id, ')')) AS supplier"),
                'contacts.id'
                    );
        } else {
            $all_contacts->select(
                'contacts.id',
                DB::raw("CONCAT(contacts.name, ' (', contacts.supplier_business_name, ')') as supplier")
                );
        }

        if (auth()->check() && ! auth()->user()->can('supplier.view') && auth()->user()->can('supplier.view_own')) {
            $all_contacts->onlyOwnContact();
        }

        $suppliers = $all_contacts->pluck('supplier', 'id');

        //Prepend none
        if ($prepend_none) {
            $suppliers = $suppliers->prepend(__('lang_v1.none'), '');
        }

        return $suppliers;
    }

    /**
     * Return list of customers dropdown for a business
     *
     * @param $business_id int
     * @param $prepend_none = true (boolean)
     * @return array users
     */
    public static function customersDropdown($business_id, $prepend_none = true, $append_id = true)
    {
        $all_contacts = Contact::where('contacts.business_id', $business_id)
                        ->whereIn('contacts.type', ['customer', 'both'])
                        ->active();

        if ($append_id) {
            $all_contacts->select(
                DB::raw("IF(contacts.contact_id IS NULL OR contacts.contact_id='', CONCAT( COALESCE(contacts.supplier_business_name, ''), ' - ', contacts.name), CONCAT(COALESCE(contacts.supplier_business_name, ''), ' - ', name, ' (', contacts.contact_id, ')')) AS customer"),
                'contacts.id'
                );
        } else {
            $all_contacts->select('contacts.id', DB::raw('contacts.name as customer'));
        }

        if (auth()->check() && ! auth()->user()->can('customer.view') && auth()->user()->can('customer.view_own')) {
            $all_contacts->onlyOwnContact();
        }

        $customers = $all_contacts->pluck('customer', 'id');

        //Prepend none
        if ($prepend_none) {
            $customers = $customers->prepend(__('lang_v1.none'), '');
        }

        return $customers;
    }

    /**
     * Return list of contact type.
     *
     * @param $prepend_all = false (boolean)
     * @return array
     */
    public static function typeDropdown($prepend_all = false)
    {
        $types = [];

        if ($prepend_all) {
            $types[''] = __('lang_v1.all');
        }

        $types['customer'] = __('report.customer');
        $types['supplier'] = __('report.supplier');
        $types['both'] = __('lang_v1.both_supplier_customer');

        return $types;
    }

    /**
     * Return list of contact type by permissions.
     *
     * @return array
     */
    public static function getContactTypes()
    {
        $types = [];
        if (auth()->check() && auth()->user()->can('supplier.create')) {
            $types['supplier'] = __('report.supplier');
        }
        if (auth()->check() && auth()->user()->can('customer.create')) {
            $types['customer'] = __('report.customer');
        }
        if (auth()->check() && auth()->user()->can('supplier.create') && auth()->user()->can('customer.create')) {
            $types['both'] = __('lang_v1.both_supplier_customer');
        }

        return $types;
    }

    public function getContactAddressAttribute()
    {
        $address_array = [];
        if (! empty($this->supplier_business_name)) {
            $address_array[] = $this->supplier_business_name;
        }
        if (! empty($this->name)) {
            $address_array[] = ! empty($this->supplier_business_name) ? '<br>'.$this->name : $this->name;
        }
        if (! empty($this->address_line_1)) {
            $address_array[] = '<br>'.$this->address_line_1;
        }
        if (! empty($this->address_line_2)) {
            $address_array[] = '<br>'.$this->address_line_2;
        }
        if (! empty($this->city)) {
            $address_array[] = '<br>'.$this->city;
        }
        if (! empty($this->state)) {
            $address_array[] = $this->state;
        }
        if (! empty($this->country)) {
            $address_array[] = $this->country;
        }
        if (! empty($this->land_mark)) {
            $address_array[] = $this->land_mark;
        }
        if (! empty($this->street_name)) {
            $address_array[] = $this->street_name;
        }
        if (! empty($this->building_number)) {
            $address_array[] = $this->building_number;
        }
        if (! empty($this->additional_number)) {
            $address_array[] = $this->additional_number;
        }

        $address = '';
        if (! empty($address_array)) {
            $address = implode(', ', $address_array);
        }
        if (! empty($this->zip_code)) {
            $address .= ',<br>'.$this->zip_code;
        }

        return $address;
    }

    public function getFullNameAttribute()
    {
        $name_array = [];
        if (! empty($this->prefix)) {
            $name_array[] = $this->prefix;
        }
        if (! empty($this->first_name)) {
            $name_array[] = $this->first_name;
        }
        if (! empty($this->middle_name)) {
            $name_array[] = $this->middle_name;
        }
        if (! empty($this->last_name)) {
            $name_array[] = $this->last_name;
        }

        return implode(' ', $name_array);
    }

    public function getFullNameWithBusinessAttribute()
    {
        $name_array = [];
        if (! empty($this->prefix)) {
            $name_array[] = $this->prefix;
        }
        if (! empty($this->first_name)) {
            $name_array[] = $this->first_name;
        }
        if (! empty($this->middle_name)) {
            $name_array[] = $this->middle_name;
        }
        if (! empty($this->last_name)) {
            $name_array[] = $this->last_name;
        }

        $full_name = implode(' ', $name_array);
        $business_name = ! empty($this->supplier_business_name) ? $this->supplier_business_name.', ' : '';

        return $business_name.$full_name;
    }

    public function getContactAddressArrayAttribute()
    {
        $address_array = [];
        if (! empty($this->address_line_1)) {
            $address_array[] = $this->address_line_1;
        }
        if (! empty($this->address_line_2)) {
            $address_array[] = $this->address_line_2;
        }
        if (! empty($this->city)) {
            $address_array[] = $this->city;
        }
        if (! empty($this->state)) {
            $address_array[] = $this->state;
        }
        if (! empty($this->country)) {
            $address_array[] = $this->country;
        }
        if (! empty($this->zip_code)) {
            $address_array[] = $this->zip_code;
        }

        return $address_array;
    }

    /**
     * All user who have access to this contact
     * Applied only when selected_contacts is true for a user in
     * users table
     */
    public function userHavingAccess()
    {
        return $this->belongsToMany(\App\User::class, 'user_contact_access');
    }

    /**
     * The "master" supplier (managed by the super admin) that this
     * Contact was cloned from. Null for supplier contacts that were
     * created directly inside the business.
     */
    public function masterSupplier()
    {
        return $this->belongsTo(\App\Contact::class, 'common_supplier_id');
    }

    /**
     * Clone this Contact (expected to be a supplier) into the given
     * business as a fully-functional supplier Contact. Idempotent: if
     * the target business already has a clone (matched by
     * business_id + common_supplier_id), returns the existing clone
     * without creating a duplicate.
     *
     * @param  int  $business_id       Target business
     * @param  int  $created_by_user_id User id stamped as creator
     * @return \App\Contact             The (new or existing) clone
     */
    public function cloneToBusinessAsSupplier($business_id, $created_by_user_id)
    {
        // Reuse an existing clone if the supplier is already linked.
        $existing = static::where('business_id', $business_id)
            ->where('common_supplier_id', $this->id)
            ->first();

        if ($existing) {
            // Refresh the link in case the row was left orphan but unlinked.
            if ($existing->common_supplier_id === null) {
                $existing->common_supplier_id = $this->id;
                $existing->save();
            }
            return $existing;
        }

        return static::create([
            'business_id' => $business_id,
            'type' => 'supplier',
            'name' => $this->name,
            'supplier_business_name' => $this->supplier_business_name,
            'contact_id' => null,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'tax_number' => $this->tax_number,
            'address_line_1' => $this->address_line_1,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'zip_code' => $this->zip_code,
            'pay_term_number' => $this->pay_term_number,
            'pay_term_type' => $this->pay_term_type,
            'created_by' => $created_by_user_id,
            'common_supplier_id' => $this->id,
        ]);
    }

    /**
     * Remove the link between this master supplier and a target business.
     * The clone Contact is kept (so historical purchases remain valid) but
     * is decoupled by clearing the FK.
     */
    public function unlinkFromBusiness($business_id)
    {
        static::where('business_id', $business_id)
            ->where('common_supplier_id', $this->id)
            ->update(['common_supplier_id' => null]);
    }

    /**
     * Backfill the name on a previously-cloned supplier when the clone
     * inherited an empty `name` from the master. Uses the master's
     * supplier_business_name as the new name. Idempotent.
     *
     * @return bool true if a name was set, false otherwise
     */
    public function backfillNameFromMaster()
    {
        if (! empty($this->name)) {
            return false;
        }
        if (empty($this->common_supplier_id)) {
            return false;
        }
        $master = static::find($this->common_supplier_id);
        if (! $master) {
            return false;
        }
        $new_name = trim((string) $master->name);
        if ($new_name === '' && ! empty($master->supplier_business_name)) {
            $new_name = trim((string) $master->supplier_business_name);
        }
        if ($new_name === '') {
            return false;
        }
        $this->name = $new_name;
        $this->save();

        return true;
    }
}
