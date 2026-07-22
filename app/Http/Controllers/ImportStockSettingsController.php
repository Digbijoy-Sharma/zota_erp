<?php

namespace App\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use App\Variation;
use App\VariationLocationDetails;
use DB;
use Excel;
use Illuminate\Http\Request;

class ImportStockSettingsController extends Controller
{
    public function index()
    {
        if (! auth()->user()->can('stock_settings.import')) {
            abort(403, 'Unauthorized action.');
        }

        $stores = $this->getStoresForDropdown();

        return view('import_stock_settings.index', compact('stores'));
    }

    public function download(Request $request)
    {
        if (! auth()->user()->can('stock_settings.import')) {
            abort(403, 'Unauthorized action.');
        }

        $store_id = $request->input('store_id');

        if (empty($store_id)) {
            $output = ['success' => 0,
                'msg' => __('lang_v1.please_select_store'),
            ];

            return redirect()->back()->with('notification', $output);
        }

        $business = $this->getAuthorizedBusiness($store_id);

        if (empty($business)) {
            abort(403, 'Unauthorized action.');
        }

        $location_ids = BusinessLocation::where('business_id', $business->id)
                            ->pluck('id')
                            ->toArray();

        if (empty($location_ids)) {
            $output = ['success' => 0,
                'msg' => __('lang_v1.no_location_found_for_store'),
            ];

            return redirect()->back()->with('notification', $output);
        }

        $primary_location_id = $location_ids[0];

        $variations = Variation::join('products AS p', 'variations.product_id', '=', 'p.id')
            ->leftJoin('variation_location_details AS vld', function ($join) use ($primary_location_id) {
                $join->on('variations.id', '=', 'vld.variation_id')
                     ->where('vld.location_id', '=', $primary_location_id);
            })
            ->where('p.business_id', $business->id)
            ->where('p.type', '!=', 'modifier')
            ->select([
                'variations.id as variation_id',
                'variations.sub_sku',
                'p.name as product_name',
                'variations.name as variation_name',
                'p.type as product_type',
                'vld.min_quantity',
                'vld.max_quantity',
                'vld.movement_tag',
            ])
            ->orderBy('p.name')
            ->orderBy('variations.name')
            ->get();

        $filename = 'stock_settings_' . str_replace(' ', '_', $business->name) . '_' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($variations) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Variation ID',
                'SKU',
                'Product Name',
                'Variation',
                'Min Qty',
                'Max Qty',
                'Movement Tag',
            ]);

            foreach ($variations as $v) {
                $variation_label = $v->product_type == 'single' ? '-' : $v->variation_name;

                fputcsv($file, [
                    $v->variation_id,
                    $v->sub_sku,
                    $v->product_name,
                    $variation_label,
                    ! is_null($v->min_quantity) ? (float) $v->min_quantity : '',
                    ! is_null($v->max_quantity) ? (float) $v->max_quantity : '',
                    $v->movement_tag ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function import(Request $request)
    {
        if (! auth()->user()->can('stock_settings.import')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            ini_set('max_execution_time', 0);
            ini_set('memory_limit', -1);

            $store_id = $request->input('store_id');

            if (empty($store_id)) {
                $output = ['success' => 0,
                    'msg' => __('lang_v1.please_select_store'),
                ];

                return redirect()->back()->with('notification', $output);
            }

            $business = $this->getAuthorizedBusiness($store_id);

            if (empty($business)) {
                abort(403, 'Unauthorized action.');
            }

            $location_ids = BusinessLocation::where('business_id', $business->id)
                                ->pluck('id')
                                ->toArray();

            if (empty($location_ids)) {
                $output = ['success' => 0,
                    'msg' => __('lang_v1.no_location_found_for_store'),
                ];

                return redirect()->back()->with('notification', $output);
            }

            if (! $request->hasFile('stock_settings_csv')) {
                $output = ['success' => 0,
                    'msg' => __('lang_v1.please_select_file'),
                ];

                return redirect()->back()->with('notification', $output);
            }

            $file = $request->file('stock_settings_csv');
            $parsed_array = Excel::toArray([], $file);

            $imported_data = array_splice($parsed_array[0], 1);

            if (empty($imported_data)) {
                $output = ['success' => 0,
                    'msg' => __('lang_v1.no_data_found_in_file'),
                ];

                return redirect()->back()->with('notification', $output);
            }

            $valid_movement_tags = ['SFM', 'FM', 'NFM', 'SM'];
            $updated = 0;
            $skipped = 0;
            $errors = [];

            DB::beginTransaction();

            foreach ($imported_data as $key => $value) {
                $row_no = $key + 2;

                if (empty($value[0])) {
                    $errors[] = __('lang_v1.row_no') . " $row_no: " . __('lang_v1.variation_id_missing');
                    $skipped++;
                    continue;
                }

                $variation_id = (int) $value[0];

                $variation = Variation::join('products AS p', 'variations.product_id', '=', 'p.id')
                    ->where('variations.id', $variation_id)
                    ->where('p.business_id', $business->id)
                    ->select(['variations.id', 'variations.product_id', 'variations.product_variation_id'])
                    ->first();

                if (empty($variation)) {
                    $errors[] = __('lang_v1.row_no') . " $row_no: " . __('lang_v1.variation_not_found', ['sku' => $value[1] ?? $variation_id]);
                    $skipped++;
                    continue;
                }

                $min_qty = isset($value[4]) && $value[4] !== '' ? $value[4] : 0;
                $max_qty = isset($value[5]) && $value[5] !== '' ? $value[5] : 0;
                $movement_tag = isset($value[6]) && ! empty(trim($value[6])) ? strtoupper(trim($value[6])) : null;

                if (! is_numeric($min_qty) || $min_qty < 0) {
                    $errors[] = __('lang_v1.row_no') . " $row_no: " . __('lang_v1.invalid_min_qty');
                    $skipped++;
                    continue;
                }

                if (! is_numeric($max_qty) || $max_qty < 0) {
                    $errors[] = __('lang_v1.row_no') . " $row_no: " . __('lang_v1.invalid_max_qty');
                    $skipped++;
                    continue;
                }

                if (! is_null($movement_tag) && ! in_array($movement_tag, $valid_movement_tags)) {
                    $errors[] = __('lang_v1.row_no') . " $row_no: " . __('lang_v1.invalid_movement_tag', ['tag' => $movement_tag]);
                    $skipped++;
                    continue;
                }

                foreach ($location_ids as $loc_id) {
                    VariationLocationDetails::updateOrCreate(
                        [
                            'variation_id' => $variation->id,
                            'location_id' => $loc_id,
                        ],
                        [
                            'product_id' => $variation->product_id,
                            'product_variation_id' => $variation->product_variation_id,
                            'min_quantity' => (float) $min_qty,
                            'max_quantity' => (float) $max_qty,
                            'movement_tag' => $movement_tag,
                            'min_max_source' => 'manual',
                        ]
                    );
                }

                $updated++;
            }

            DB::commit();

            $msg = __('lang_v1.stock_settings_import_success', ['updated' => $updated, 'skipped' => $skipped]);

            if (! empty($errors)) {
                $msg .= '<br><br><strong>' . __('lang_v1.errors') . ':</strong><br>' . implode('<br>', array_slice($errors, 0, 20));
                if (count($errors) > 20) {
                    $msg .= '<br>... ' . __('lang_v1.and_more', ['count' => count($errors) - 20]);
                }
            }

            $output = ['success' => 1,
                'msg' => $msg,
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong') . ': ' . $e->getMessage(),
            ];
        }

        return redirect()->back()->with('notification', $output);
    }

    private function isSuperadmin()
    {
        $user = auth()->user();
        if (empty($user)) {
            return false;
        }
        $administrator_list = config('constants.administrator_usernames');
        if (empty($administrator_list)) {
            return false;
        }

        return in_array(
            strtolower($user->username),
            explode(',', strtolower($administrator_list))
        );
    }

    private function getStoresForDropdown()
    {
        if ($this->isSuperadmin()) {
            return Business::where('is_active', 1)->pluck('name', 'id');
        }

        $business_id = request()->session()->get('user.business_id');

        return Business::where('id', $business_id)
                    ->where('is_active', 1)
                    ->pluck('name', 'id');
    }

    private function getAuthorizedBusiness($store_id)
    {
        $query = Business::where('id', $store_id)->where('is_active', 1);

        if (! $this->isSuperadmin()) {
            $business_id = request()->session()->get('user.business_id');
            $query->where('id', $business_id);
        }

        return $query->first();
    }
}
