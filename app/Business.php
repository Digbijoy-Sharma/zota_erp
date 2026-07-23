<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'business';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id', 'woocommerce_api_settings'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = ['woocommerce_api_settings'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'ref_no_prefixes' => 'array',
        'enabled_modules' => 'array',
        'email_settings' => 'array',
        'sms_settings' => 'array',
        'common_settings' => 'array',
        'weighing_scale_setting' => 'array',
    ];

    /**
     * Returns the date formats
     */
    public static function date_formats()
    {
        return [
            'd-m-Y' => 'dd-mm-yyyy',
            'm-d-Y' => 'mm-dd-yyyy',
            'd/m/Y' => 'dd/mm/yyyy',
            'm/d/Y' => 'mm/dd/yyyy',
        ];
    }

    /**
     * Get the owner details
     */
    public function owner()
    {
        return $this->hasOne(\App\User::class, 'id', 'owner_id');
    }

    /**
     * Get the Business currency.
     */
    public function currency()
    {
        return $this->belongsTo(\App\Currency::class);
    }

    /**
     * Get the Business currency.
     */
    public function locations()
    {
        return $this->hasMany(\App\BusinessLocation::class);
    }

    /**
     * Get the Business printers.
     */
    public function printers()
    {
        return $this->hasMany(\App\Printer::class);
    }

    /**
     * Get the Business subscriptions.
     */
    public function subscriptions()
    {
        return $this->hasMany('\Modules\Superadmin\Entities\Subscription');
    }

    /**
     * Suppliers that were cloned into this business from the super
     * admin's master supplier list. A supplier is "assigned" when a
     * Contact row exists in this business with common_supplier_id set.
     */
    public function assignedCommonSuppliers()
    {
        return $this->hasMany(\App\Contact::class, 'business_id')
            ->whereNotNull('common_supplier_id');
    }

    /**
     * Creates a new business based on the input provided.
     *
     * @return object
     */
    public static function create_business($details)
    {
        $business = Business::create($details);

        return $business;
    }

    /**
     * Updates a business based on the input provided.
     *
     * @param  int  $business_id
     * @param  array  $details
     * @return object
     */
    public static function update_business($business_id, $details)
    {
        if (! empty($details)) {
            Business::where('id', $business_id)
                ->update($details);
        }
    }

    /**
     * The chain "template" business — the lowest-id business, which is
     * the superadmin's own business and the source of chain-wide
     * defaults (mirrors MovementTagConfig::templateBusinessId()).
     */
    public static function templateBusinessId()
    {
        return static::orderBy('id')->value('id');
    }

    /**
     * Effective auto-PO frequency (in days) for this store:
     *   1. the store's own per-store override (auto_po_frequency_days), else
     *   2. the chain-wide default held on the template business, else
     *   3. null  =>  auto-PO is disabled for this store.
     */
    public function effectiveAutoPoFrequencyDays()
    {
        if (! empty($this->auto_po_frequency_days)) {
            return (int) $this->auto_po_frequency_days;
        }

        $template_id = static::templateBusinessId();

        // The template itself has no higher default to inherit from.
        if ((int) $this->id === (int) $template_id) {
            return null;
        }

        $default = static::where('id', $template_id)->value('auto_po_frequency_days');

        return ! empty($default) ? (int) $default : null;
    }

    public function getBusinessAddressAttribute()
    {
        $location = $this->locations->first();
        $address = $location->landmark.', '.$location->city.
        ', '.$location->state.'<br>'.$location->country.', '.$location->zip_code;

        return $address;
    }
}
