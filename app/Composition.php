<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Composition extends Model
{
    protected $table = 'compositions';

    protected $guarded = ['id'];

    /**
     * Business this composition belongs to.
     */
    public function business()
    {
        return $this->belongsTo(\App\Business::class);
    }

    /**
     * Salts that make up this composition.
     */
    public function salts()
    {
        return $this->belongsToMany(\App\Salt::class, 'composition_salt', 'composition_id', 'salt_id');
    }

    /**
     * Build a deterministic composition name from a list of salt names.
     * Names are trimmed, lower-cased for comparison, and duplicates are
     * removed. The result uses the original-cased form of the first
     * occurrence joined with "+" (e.g. "Paracetamol + Ibuprofen").
     *
     * @param  array  $saltNames
     * @return string
     */
    public static function buildNameFromSaltNames(array $saltNames)
    {
        $cleaned = [];
        $seen = [];
        foreach ($saltNames as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $cleaned[] = $name;
        }

        return implode(' + ', $cleaned);
    }

    /**
     * Build a composition name from a list of salt ids (in the user's
     * order of entry) by resolving each id to its name.
     *
     * @param  array  $saltIds
     * @return string
     */
    public static function buildNameFromSaltIds(array $saltIds)
    {
        $salts = Salt::whereIn('id', $saltIds)->get()->keyBy('id');
        $names = [];
        foreach ($saltIds as $id) {
            if (isset($salts[$id])) {
                $names[] = $salts[$id]->name;
            }
        }

        return self::buildNameFromSaltNames($names);
    }

    /**
     * Return list of compositions for a business, suitable for a dropdown.
     *
     * @param  int  $business_id
     * @param  bool  $show_none
     * @return \Illuminate\Support\Collection
     */
    public static function forDropdown($business_id, $show_none = false)
    {
        $compositions = self::where('business_id', $business_id)
            ->orderBy('name')
            ->select('id', 'name')
            ->get();

        $dropdown = $compositions->pluck('name', 'id');
        if ($show_none) {
            $dropdown->prepend('', '');
        }

        return $dropdown;
    }
}
