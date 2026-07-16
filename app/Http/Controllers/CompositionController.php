<?php

namespace App\Http\Controllers;

use App\Composition;
use App\Salt;
use App\Utils\Util;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use DB;

class CompositionController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $commonUtil;

    public function __construct(Util $commonUtil)
    {
        $this->commonUtil = $commonUtil;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (! auth()->user()->can('product.view') && ! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');

            $compositions = Composition::where('business_id', $business_id)
                ->withCount('salts');

            return Datatables::of($compositions)
                ->addColumn('action', function ($row) {
                    $editUrl = action([\App\Http\Controllers\CompositionController::class, 'edit'], [$row->id]);
                    $showUrl = action([\App\Http\Controllers\CompositionController::class, 'show'], [$row->id]);
                    $deleteUrl = action([\App\Http\Controllers\CompositionController::class, 'destroy'], [$row->id]);
                    $html  = '<a href="' . $editUrl . '" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary edit_composition_button" data-href="' . $editUrl . '"><i class="glyphicon glyphicon-edit"></i> ' . __('messages.edit') . '</a> &nbsp;';
                    $html .= '<a href="' . $showUrl . '" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info view_composition_button"><i class="fa fa-eye"></i> ' . __('messages.view') . '</a> &nbsp;';
                    $html .= '<a href="' . $deleteUrl . '" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_composition_button" data-href="' . $deleteUrl . '"><i class="glyphicon glyphicon-trash"></i> ' . __('messages.delete') . '</a>';

                    return $html;
                })
                ->editColumn('name', function ($row) {
                    return '<strong>' . e($row->name) . '</strong>';
                })
                ->editColumn('salts_count', function ($row) {
                    return '<span class="label label-info">' . (int) ($row->salts_count ?? 0) . '</span>';
                })
                ->removeColumn('id')
                ->rawColumns(['action', 'name', 'salts_count'])
                ->make(true);
        }

        return view('composition.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $salts = Salt::forDropdown($business_id);

        return view('composition.create')
            ->with(compact('salts'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');

            $salt_ids = $this->extractSaltIds($request);

            if (empty($salt_ids)) {
                $output = [
                    'success' => false,
                    'msg' => __('composition.need_at_least_one_salt'),
                ];

                return response()->json($output);
            }

            // Resolve names and build the composition name.
            $salts = Salt::whereIn('id', $salt_ids)->get()->keyBy('id');
            $ordered_names = [];
            foreach ($salt_ids as $sid) {
                if (isset($salts[$sid])) {
                    $ordered_names[] = $salts[$sid]->name;
                }
            }
            $name = Composition::buildNameFromSaltNames($ordered_names);

            if ($name === '') {
                $output = [
                    'success' => false,
                    'msg' => __('composition.need_at_least_one_salt'),
                ];

                return response()->json($output);
            }

            // Reject if a composition with the same name already exists for this business.
            $existing = Composition::where('business_id', $business_id)
                ->where('name', $name)
                ->first();
            if ($existing) {
                $output = [
                    'success' => false,
                    'msg' => __('composition.duplicate_name'),
                ];

                return response()->json($output);
            }

            DB::beginTransaction();
            $composition = Composition::create([
                'business_id' => $business_id,
                'name' => $name,
                'created_by' => $request->session()->get('user.id'),
            ]);
            $composition->salts()->sync($salt_ids);
            DB::commit();

            $output = [
                'success' => true,
                'data' => $composition,
                'msg' => __('composition.added_success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return response()->json($output);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (! auth()->user()->can('product.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $composition = Composition::where('business_id', $business_id)
            ->with('salts')
            ->findOrFail($id);

        return view('composition.show')->with(compact('composition'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $composition = Composition::where('business_id', $business_id)
            ->with('salts')
            ->findOrFail($id);
        $salts = Salt::forDropdown($business_id);

        return view('composition.edit')->with(compact('composition', 'salts'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');
            $composition = Composition::where('business_id', $business_id)
                ->findOrFail($id);

            $salt_ids = $this->extractSaltIds($request);

            if (empty($salt_ids)) {
                $output = [
                    'success' => false,
                    'msg' => __('composition.need_at_least_one_salt'),
                ];

                return response()->json($output);
            }

            $salts = Salt::whereIn('id', $salt_ids)->get()->keyBy('id');
            $ordered_names = [];
            foreach ($salt_ids as $sid) {
                if (isset($salts[$sid])) {
                    $ordered_names[] = $salts[$sid]->name;
                }
            }
            $name = Composition::buildNameFromSaltNames($ordered_names);

            if ($name === '') {
                $output = [
                    'success' => false,
                    'msg' => __('composition.need_at_least_one_salt'),
                ];

                return response()->json($output);
            }

            // Reject duplicate-name conflict (but allow self).
            $duplicate = Composition::where('business_id', $business_id)
                ->where('name', $name)
                ->where('id', '!=', $composition->id)
                ->first();
            if ($duplicate) {
                $output = [
                    'success' => false,
                    'msg' => __('composition.duplicate_name'),
                ];

                return response()->json($output);
            }

            DB::beginTransaction();
            $composition->name = $name;
            $composition->save();
            $composition->salts()->sync($salt_ids);
            DB::commit();

            $output = [
                'success' => true,
                'msg' => __('composition.updated_success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return response()->json($output);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('product.delete')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');
                $composition = Composition::where('business_id', $business_id)
                    ->findOrFail($id);

                // Block delete if any product is still using this composition
                $in_use = \App\Product::where('composition_id', $composition->id)->exists();
                if ($in_use) {
                    $output = [
                        'success' => false,
                        'msg' => __('composition.cannot_delete_in_use'),
                    ];

                    return response()->json($output);
                }

                DB::beginTransaction();
                $composition->salts()->detach();
                $composition->delete();
                DB::commit();

                $output = [
                    'success' => true,
                    'msg' => __('composition.deleted_success'),
                ];
            } catch (\Exception $e) {
                DB::rollBack();
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
                $output = [
                    'success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return response()->json($output);
        }
    }

    /**
     * Extract the list of salt ids from the request.
     * Accepts:
     *  - 'salt_ids'   => [id, id, ...]   (preferred)
     *  - 'salts'      => [name, name, ..] (free-text input from the form's
     *                     "add new salt" button; missing salts are
     *                     auto-created for the business).
     */
    private function extractSaltIds(Request $request)
    {
        $raw = $request->input('salt_ids', []);

        // If hidden salt_ids hidden field is empty, fall back to "salts" free-text list.
        if (empty($raw) && $request->has('salts')) {
            $names = (array) $request->input('salts', []);
            $business_id = $request->session()->get('user.business_id');
            $raw = [];
            foreach ($names as $name) {
                $name = trim((string) $name);
                if ($name === '') {
                    continue;
                }
                $salt = Salt::firstOrCreate(
                    ['business_id' => $business_id, 'name' => $name],
                    ['created_by' => $request->session()->get('user.id')]
                );
                $raw[] = $salt->id;
            }
        }

        // Normalize: keep order, drop blanks, dedupe.
        $out = [];
        $seen = [];
        foreach ((array) $raw as $id) {
            $id = (int) $id;
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $id;
        }

        return $out;
    }
}
