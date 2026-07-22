<?php

namespace App\Http\Controllers;

use App\Division;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class DivisionController extends Controller
{
    public function index()
    {
        if (! auth()->user()->can('division.view') && ! auth()->user()->can('division.create')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');

            $divisions = Division::where('business_id', $business_id)
                        ->select(['name', 'description', 'id']);

            return Datatables::of($divisions)
                ->addColumn(
                    'action',
                    '@can("division.update")
                    <button data-href="{{action(\'App\Http\Controllers\DivisionController@edit\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary edit_division_button"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</button>
                        &nbsp;
                    @endcan
                    @can("division.delete")
                        <button data-href="{{action(\'App\Http\Controllers\DivisionController@destroy\', [$id])}}" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_division_button"><i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")</button>
                    @endcan'
                )
                ->removeColumn('id')
                ->rawColumns([2])
                ->make(false);
        }

        return view('division.index');
    }

    public function create()
    {
        if (! auth()->user()->can('division.create')) {
            abort(403, 'Unauthorized action.');
        }

        $quick_add = ! empty(request()->input('quick_add'));

        return view('division.create')->with(compact('quick_add'));
    }

    public function store(Request $request)
    {
        if (! auth()->user()->can('division.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'description']);
            $input['business_id'] = $request->session()->get('user.business_id');
            $input['created_by'] = $request->session()->get('user.id');

            $division = Division::create($input);
            $output = ['success' => true,
                'data' => $division,
                'msg' => __('lang_v1.division_added_success'),
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
        if (! auth()->user()->can('division.update')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $division = Division::where('business_id', $business_id)->find($id);

            return view('division.edit')->with(compact('division'));
        }
    }

    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('division.update')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $input = $request->only(['name', 'description']);
                $business_id = $request->session()->get('user.business_id');

                $division = Division::where('business_id', $business_id)->findOrFail($id);
                $division->name = $input['name'];
                $division->description = $input['description'];
                $division->save();

                $output = ['success' => true,
                    'msg' => __('lang_v1.division_updated_success'),
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
        if (! auth()->user()->can('division.delete')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->user()->business_id;

                $division = Division::where('business_id', $business_id)->findOrFail($id);
                $division->delete();

                $output = ['success' => true,
                    'msg' => __('lang_v1.division_deleted_success'),
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
