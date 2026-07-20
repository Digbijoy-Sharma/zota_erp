<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InvoiceScheme extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Master scheme this row mirrors (store-side copies of a
     * superadmin-managed scheme). The invoice counter lives on the
     * master; mirrors only carry the format for local dropdowns.
     */
    public function master()
    {
        return $this->belongsTo(InvoiceScheme::class, 'master_invoice_scheme_id');
    }

    /**
     * Store-side mirrors of this (master) scheme.
     */
    public function mirrors()
    {
        return $this->hasMany(InvoiceScheme::class, 'master_invoice_scheme_id');
    }

    /**
     * Returns list of invoice schemes in array format
     */
    public static function forDropdown($business_id)
    {
        $dropdown = InvoiceScheme::where('business_id', $business_id)
                                ->pluck('name', 'id');

        return $dropdown;
    }

    /**
     * Retrieves the default invoice scheme
     */
    public static function getDefault($business_id)
    {
        $default = InvoiceScheme::where('business_id', $business_id)
                                ->where('is_default', 1)
                                ->first();

        return $default;
    }
}
