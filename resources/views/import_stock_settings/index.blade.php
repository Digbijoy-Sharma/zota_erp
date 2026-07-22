@extends('layouts.app')
@section('title', __('lang_v1.import_stock_settings'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.import_stock_settings')
        <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('lang_v1.import_stock_settings_help')</small>
    </h1>
</section>

<!-- Main content -->
<section class="content">

    @if (session('notification') || !empty($notification))
        <div class="row">
            <div class="col-sm-12">
                <div class="alert alert-{{ !empty(session('notification.success')) || !empty($notification['success']) ? 'success' : 'danger' }} alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    @if(!empty($notification['msg']))
                        {!! $notification['msg'] !!}
                    @elseif(session('notification.msg'))
                        {!! session('notification.msg') !!}
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Step 1: Download CSV --}}
    <div class="row">
        <div class="col-sm-12">
            @component('components.widget', ['class' => 'box-success', 'title' => __('lang_v1.step_1_download_csv')])
                {!! Form::open(['url' => action([\App\Http\Controllers\ImportStockSettingsController::class, 'download']), 'method' => 'post']) !!}
                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-group">
                                {!! Form::label('download_store_id', __('lang_v1.select_store') . ':') !!}
                                {!! Form::select('store_id', $stores, null, ['class' => 'form-control select2', 'placeholder' => __('lang_v1.please_select'), 'required', 'id' => 'download_store_id']); !!}
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <br>
                            <button type="submit" class="tw-dw-btn tw-dw-btn-success tw-text-white">
                                <i class="fa fa-download"></i> @lang('lang_v1.download_csv')
                            </button>
                        </div>
                    </div>
                {!! Form::close() !!}
                <div class="row">
                    <div class="col-sm-12">
                        <p class="text-muted">
                            <i class="fa fa-info-circle"></i> @lang('lang_v1.download_csv_instruction')
                        </p>
                    </div>
                </div>
            @endcomponent
        </div>
    </div>

    {{-- Step 2: Upload CSV --}}
    <div class="row">
        <div class="col-sm-12">
            @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.step_2_upload_csv')])
                {!! Form::open(['url' => action([\App\Http\Controllers\ImportStockSettingsController::class, 'import']), 'method' => 'post', 'enctype' => 'multipart/form-data']) !!}
                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-group">
                                {!! Form::label('upload_store_id', __('lang_v1.select_store') . ':') !!}
                                {!! Form::select('store_id', $stores, null, ['class' => 'form-control select2', 'placeholder' => __('lang_v1.please_select'), 'required', 'id' => 'upload_store_id']); !!}
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                {!! Form::label('stock_settings_csv', __('product.file_to_import') . ':') !!}
                                {!! Form::file('stock_settings_csv', ['accept' => '.xls, .xlsx, .csv', 'required' => 'required']); !!}
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <br>
                            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">
                                <i class="fa fa-upload"></i> @lang('lang_v1.import_settings')
                            </button>
                        </div>
                    </div>
                {!! Form::close() !!}
            @endcomponent
        </div>
    </div>

    {{-- Instructions --}}
    <div class="row">
        <div class="col-sm-12">
            @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.instructions')])
                <strong>@lang('lang_v1.stock_settings_instruction_line1')</strong><br>
                @lang('lang_v1.stock_settings_instruction_line2')
                <br><br>
                <table class="table table-striped">
                    <tr>
                        <th>@lang('lang_v1.col_no')</th>
                        <th>@lang('lang_v1.col_name')</th>
                        <th>@lang('lang_v1.instruction')</th>
                    </tr>
                    <tr>
                        <td>1</td>
                        <td>Variation ID <small class="text-muted">(@lang('lang_v1.required'))</small></td>
                        <td>@lang('lang_v1.variation_id_ins')</td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td>@lang('product.sku') <small class="text-muted">(@lang('lang_v1.read_only'))</small></td>
                        <td>@lang('lang_v1.sku_ref_ins')</td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td>@lang('product.product_name') <small class="text-muted">(@lang('lang_v1.read_only'))</small></td>
                        <td>@lang('lang_v1.product_name_ref_ins')</td>
                    </tr>
                    <tr>
                        <td>4</td>
                        <td>@lang('product.variation') <small class="text-muted">(@lang('lang_v1.read_only'))</small></td>
                        <td>@lang('lang_v1.variation_ref_ins')</td>
                    </tr>
                    <tr>
                        <td>5</td>
                        <td>@lang('lang_v1.min_quantity') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td>@lang('lang_v1.min_qty_ins')</td>
                    </tr>
                    <tr>
                        <td>6</td>
                        <td>@lang('lang_v1.max_quantity') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td>@lang('lang_v1.max_qty_ins')</td>
                    </tr>
                    <tr>
                        <td>7</td>
                        <td>@lang('lang_v1.movement_tag') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td>@lang('lang_v1.movement_tag_ins')<br>
                            <strong>@lang('lang_v1.available_options'): SFM, FM, NFM, SM</strong>
                        </td>
                    </tr>
                </table>
            @endcomponent
        </div>
    </div>
</section>

@endsection
