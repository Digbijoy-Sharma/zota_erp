<?php

namespace App\Http\Controllers;

use App\InvoiceLayout;
use App\InvoiceScheme;
use Datatables;
use Illuminate\Http\Request;

class InvoiceSchemeController extends Controller
{
    protected $number_types;

    public function __construct()
    {
        $this->number_types = ['sequential' => __('invoice.sequential'), 'random'=> __('invoice.random')];
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! auth()->user()->can('invoice_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        if (request()->ajax()) {
            $schemes = InvoiceScheme::where('business_id', $business_id)
                            ->select(['id', 'name', 'scheme_type', 'prefix', 'number_type', 'start_number', 'invoice_count', 'total_digits', 'is_default']);

            return Datatables::of($schemes)
                ->addColumn(
                    'action',
                    '<button type="button" data-href="{{action(\'App\Http\Controllers\InvoiceSchemeController@edit\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary btn-modal" data-container=".invoice_edit_modal"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</button>
                        &nbsp;
                        <button type="button" data-href="{{action(\'App\Http\Controllers\InvoiceSchemeController@destroy\', [$id])}}" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_invoice_button" @if($is_default) disabled @endif><i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")</button>&nbsp;
                        @if($is_default)
                            <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-accent" disabled><i class="fa fa-check-square-o" aria-hidden="true"></i> @lang("barcode.default")</button>
                        @else
                            <button class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info set_default_invoice" data-href="{{action(\'App\Http\Controllers\InvoiceSchemeController@setDefault\', [$id])}}">@lang("barcode.set_as_default")</button>
                        @endif
                        '
                )
                ->editColumn('number_type', function ($row) {
                    return $this->number_types[$row->number_type];
                })
                ->editColumn('prefix', function ($row) {
                    if ($row->scheme_type == 'year') {
                        return $row->prefix.date('Y').config('constants.invoice_scheme_separator');
                    } else {
                        return $row->prefix;
                    }
                })
                ->editColumn('name', function ($row) {
                    if ($row->is_default == 1) {
                        return $row->name.' &nbsp; <span class="label label-success">'.__('barcode.default').'</span>';
                    } else {
                        return $row->name;
                    }
                })
                ->removeColumn('id')
                ->removeColumn('is_default')
                ->removeColumn('scheme_type')
                ->rawColumns([6, 0])
                ->make(false);
        }

        $invoice_layouts = InvoiceLayout::where('business_id', $business_id)
                                        ->with(['locations'])
                                        ->get();

        return view('invoice_scheme.index')
                    ->with(compact('invoice_layouts'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! auth()->user()->can('invoice_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        $number_types = $this->number_types;
        return view('invoice_scheme.create')->with(compact('number_types'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('invoice_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'scheme_type', 'prefix', 'start_number', 'total_digits', 'number_type']);
            $business_id = $request->session()->get('user.business_id');
            $input['business_id'] = $business_id;

            // Stores whose numbering is centrally managed (a mirror of
            // a super-admin master scheme exists) must not create side
            // schemes — every invoice has to come from the shared
            // GST-wise series.
            $has_mirror = InvoiceScheme::where('business_id', $business_id)
                ->whereNotNull('master_invoice_scheme_id')
                ->exists();
            if ($has_mirror) {
                return ['success' => false,
                    'msg' => __('superadmin::lang.scheme_creation_blocked'),
                ];
            }

            $input['start_number'] = ($input['number_type'] == 'aleatory') ? '' : $input['start_number'];

            if (! empty($request->input('is_default'))) {
                //get_default
                $default = InvoiceScheme::where('business_id', $business_id)
                                ->where('is_default', 1)
                                ->update(['is_default' => 0]);
                $input['is_default'] = 1;
            }
            InvoiceScheme::create($input);
            $output = ['success' => true,
                'msg' => __('invoice.added_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (! auth()->user()->can('invoice_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('invoice_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $invoice = InvoiceScheme::where('business_id', $business_id)->find($id);

        $number_types = $this->number_types;

        return view('invoice_scheme.edit')
            ->with(compact('invoice', 'number_types'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('invoice_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'scheme_type', 'prefix', 'start_number', 'total_digits', 'number_type']);

            $input['start_number'] = ($input['number_type'] == 'aleatory') ? '' : $input['start_number'];

            $business_id = $request->session()->get('user.business_id');
            $scheme = InvoiceScheme::where('business_id', $business_id)->findOrFail($id);

            // Mirrors of a super-admin master scheme are read-only on
            // the store side — the master defines the format.
            if (! empty($scheme->master_invoice_scheme_id)) {
                return ['success' => false,
                    'msg' => __('superadmin::lang.mirror_scheme_locked'),
                ];
            }

            $scheme->update($input);

            // If this scheme is a master with store mirrors, push the
            // format change to every mirror (single bulk query).
            if (class_exists(\Modules\Superadmin\Http\Controllers\InvoiceAssignmentController::class)) {
                \Modules\Superadmin\Http\Controllers\InvoiceAssignmentController::syncMirrorsForScheme($scheme->refresh());
            }

            $output = ['success' => true,
                'msg' => __('invoice.updated_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('invoice_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');
                $invoice = InvoiceScheme::where('business_id', $business_id)->findOrFail($id);

                // Centrally managed mirrors cannot be deleted by the
                // store — that would detach it from the shared series.
                if (! empty($invoice->master_invoice_scheme_id)) {
                    return ['success' => false,
                        'msg' => __('superadmin::lang.mirror_scheme_locked'),
                    ];
                }

                if ($invoice->is_default != 1) {
                    $invoice->delete();
                    $output = ['success' => true,
                        'msg' => __('invoice.deleted_success'),
                    ];
                } else {
                    $output = ['success' => false,
                        'msg' => __('messages.something_went_wrong'),
                    ];
                }
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Sets invoice scheme setting as default
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function setDefault($id)
    {
        if (! auth()->user()->can('invoice_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');
                $invoice = InvoiceScheme::where('business_id', $business_id)->findOrFail($id);

                // When the business is on a centrally managed shared
                // series, its mirror must stay the default — every
                // invoice has to be numbered from that series.
                $mirror = InvoiceScheme::where('business_id', $business_id)
                    ->whereNotNull('master_invoice_scheme_id')
                    ->first();
                if (! empty($mirror) && $mirror->id != $invoice->id) {
                    return ['success' => false,
                        'msg' => __('superadmin::lang.mirror_scheme_locked'),
                    ];
                }

                //get_default
                $default = InvoiceScheme::where('business_id', $business_id)
                                ->where('is_default', 1)
                                 ->update(['is_default' => 0]);

                $invoice->is_default = 1;
                $invoice->save();

                $output = ['success' => true,
                    'msg' => __('barcode.default_set_success'),
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
