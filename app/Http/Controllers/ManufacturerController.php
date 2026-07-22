<?php

namespace App\Http\Controllers;

use App\Manufacturer;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class ManufacturerController extends Controller
{
    public function index()
    {
        if (! auth()->user()->can('manufacturer.view') && ! auth()->user()->can('manufacturer.create')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');

            $manufacturers = Manufacturer::where('business_id', $business_id)
                        ->select(['name', 'description', 'id']);

            return Datatables::of($manufacturers)
                ->addColumn(
                    'action',
                    '@can("manufacturer.update")
                    <button data-href="{{action(\'App\Http\Controllers\ManufacturerController@edit\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary edit_manufacturer_button"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</button>
                        &nbsp;
                    @endcan
                    @can("manufacturer.delete")
                        <button data-href="{{action(\'App\Http\Controllers\ManufacturerController@destroy\', [$id])}}" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_manufacturer_button"><i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")</button>
                    @endcan'
                )
                ->removeColumn('id')
                ->rawColumns([2])
                ->make(false);
        }

        return view('manufacturer.index');
    }

    public function create()
    {
        if (! auth()->user()->can('manufacturer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $quick_add = ! empty(request()->input('quick_add'));

        return view('manufacturer.create')->with(compact('quick_add'));
    }

    public function store(Request $request)
    {
        if (! auth()->user()->can('manufacturer.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'description']);
            $input['business_id'] = $request->session()->get('user.business_id');
            $input['created_by'] = $request->session()->get('user.id');

            $manufacturer = Manufacturer::create($input);
            $output = ['success' => true,
                'data' => $manufacturer,
                'msg' => __('lang_v1.manufacturer_added_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    public function edit($id)
    {
        if (! auth()->user()->can('manufacturer.update')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $manufacturer = Manufacturer::where('business_id', $business_id)->find($id);

            return view('manufacturer.edit')->with(compact('manufacturer'));
        }
    }

    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('manufacturer.update')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $input = $request->only(['name', 'description']);
                $business_id = $request->session()->get('user.business_id');

                $manufacturer = Manufacturer::where('business_id', $business_id)->findOrFail($id);
                $manufacturer->name = $input['name'];
                $manufacturer->description = $input['description'];
                $manufacturer->save();

                $output = ['success' => true,
                    'msg' => __('lang_v1.manufacturer_updated_success'),
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

    public function destroy($id)
    {
        if (! auth()->user()->can('manufacturer.delete')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->user()->business_id;

                $manufacturer = Manufacturer::where('business_id', $business_id)->findOrFail($id);
                $manufacturer->delete();

                $output = ['success' => true,
                    'msg' => __('lang_v1.manufacturer_deleted_success'),
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
}
