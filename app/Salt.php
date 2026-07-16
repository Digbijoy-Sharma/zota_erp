<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Salt extends Model
{
    protected $table = 'salts';

    protected $guarded = ['id'];

    /**
     * Business this salt belongs to.
     */
    public function business()
    {
        return $this->belongsTo(\App\Business::class);
    }

    /**
     * Compositions that include this salt.
     */
    public function compositions()
    {
        return $this->belongsToMany(\App\Composition::class, 'composition_salt', 'salt_id', 'composition_id');
    }

    /**
     * Return list of salts for a business, suitable for a dropdown.
     *
     * @param  int  $business_id
     * @return \Illuminate\Support\Collection
     */
    public static function forDropdown($business_id)
    {
        return self::where('business_id', $business_id)
            ->orderBy('name')
            ->pluck('name', 'id');
    }
}
