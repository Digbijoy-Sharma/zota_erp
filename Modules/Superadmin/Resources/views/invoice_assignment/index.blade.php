@extends('layouts.app')
@section('title', __('superadmin::lang.superadmin') . ' | ' . __('superadmin::lang.invoice_assignment'))

@section('content')
    @include('superadmin::layouts.nav')

<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('superadmin::lang.invoice_assignment')
        <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('superadmin::lang.invoice_assignment_help')</small>
    </h1>
</section>

<section class="content">

    @component('components.widget', ['class' => 'box-primary', 'title' => __('superadmin::lang.master_invoice_schemes')])
        @slot('tool')
            <div class="box-tools">
                <input type="text" class="form-control input-sm" id="search_schemes" placeholder="@lang('lang_v1.search')...">
            </div>
        @endslot
        <p class="text-muted">@lang('superadmin::lang.master_invoice_schemes_help')</p>
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="schemes_table">
                <thead>
                    <tr>
                        <th>@lang('invoice.name')</th>
                        <th>@lang('superadmin::lang.next_invoice_no')</th>
                        <th>@lang('superadmin::lang.gst_number') / @lang('superadmin::lang.state')</th>
                        <th>@lang('superadmin::lang.attached_stores')</th>
                        <th>@lang('messages.action')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($master_schemes as $scheme)
                        @php
                            $preview_prefix = $scheme->scheme_type == 'blank' ? $scheme->prefix : $scheme->prefix . date('Y') . config('constants.invoice_scheme_separator');
                            $next_no = $preview_prefix . str_pad($scheme->start_number + $scheme->invoice_count, $scheme->total_digits, '0', STR_PAD_LEFT);
                        @endphp
                        <tr>
                            <td>{{ $scheme->name }} @if($scheme->is_default)<span class="label label-success">@lang('barcode.default')</span>@endif</td>
                            <td><code>{{ $next_no }}</code> <small class="text-muted">({{ $scheme->invoice_count }} @lang('superadmin::lang.issued'))</small></td>
                            <td>
                                {!! Form::open(['url' => action([\Modules\Superadmin\Http\Controllers\InvoiceAssignmentController::class, 'saveSchemeGst']), 'method' => 'post', 'class' => 'form-inline']) !!}
                                    {!! Form::hidden('scheme_id', $scheme->id) !!}
                                    {!! Form::text('gst_number', $scheme->gst_number, ['class' => 'form-control input-sm', 'placeholder' => __('superadmin::lang.gst_number'), 'style' => 'width:150px']) !!}
                                    {!! Form::text('state_name', $scheme->state_name, ['class' => 'form-control input-sm', 'placeholder' => __('superadmin::lang.state'), 'style' => 'width:110px']) !!}
                                    <button type="submit" class="btn btn-xs btn-primary">@lang('messages.save')</button>
                                {!! Form::close() !!}
                            </td>
                            <td>{{ $scheme->mirrors_count }}</td>
                            <td>
                                <button type="button" class="btn btn-xs btn-success assign-btn"
                                    data-scheme_id="{{ $scheme->id }}" data-scheme_name="{{ $scheme->name }}" data-gst="{{ $scheme->gst_number }}">
                                    <i class="fa fa-link"></i> @lang('superadmin::lang.assign_to_stores')
                                </button>
                                <button type="button" class="btn btn-xs btn-warning reset-btn"
                                    data-scheme_id="{{ $scheme->id }}" data-scheme_name="{{ $scheme->name }}" data-prefix="{{ $scheme->prefix }}">
                                    <i class="fa fa-refresh"></i> @lang('superadmin::lang.start_new_series')
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-muted">
            <i class="fa fa-info-circle"></i> @lang('superadmin::lang.create_scheme_hint')
        </p>
    @endcomponent

    @component('components.widget', ['class' => 'box-primary', 'title' => __('superadmin::lang.stores_and_assignment')])
        @slot('tool')
            <div class="box-tools">
                <input type="text" class="form-control input-sm" id="search_stores" placeholder="@lang('lang_v1.search')...">
            </div>
        @endslot
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="stores_table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>@lang('superadmin::lang.business_name')</th>
                        <th>@lang('superadmin::lang.gst_number')</th>
                        <th>@lang('superadmin::lang.assigned_scheme')</th>
                        <th>@lang('superadmin::lang.assigned_layout')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($businesses as $business)
                        @php
                            $loc = $business->locations->first();
                            $cur_scheme = $loc && $loc->invoice_scheme_id && isset($schemes_by_id[$loc->invoice_scheme_id]) ? $schemes_by_id[$loc->invoice_scheme_id] : null;
                            $cur_layout = $loc && $loc->invoice_layout_id && isset($layouts_by_id[$loc->invoice_layout_id]) ? $layouts_by_id[$loc->invoice_layout_id] : null;
                        @endphp
                        <tr>
                            <td>{{ $business->id }}</td>
                            <td>{{ $business->name }} @if(!$business->is_active)<span class="label label-danger">@lang('superadmin::lang.inactive')</span>@endif</td>
                            <td>
                                {!! Form::open(['url' => action([\Modules\Superadmin\Http\Controllers\InvoiceAssignmentController::class, 'saveBusinessGst']), 'method' => 'post', 'class' => 'form-inline']) !!}
                                    {!! Form::hidden('business_id', $business->id) !!}
                                    {!! Form::text('tax_number_1', $business->tax_number_1, ['class' => 'form-control input-sm', 'placeholder' => __('superadmin::lang.gst_number'), 'style' => 'width:170px']) !!}
                                    <button type="submit" class="btn btn-xs btn-primary">@lang('messages.save')</button>
                                {!! Form::close() !!}
                            </td>
                            <td>
                                @if($cur_scheme)
                                    {{ $cur_scheme->name }}
                                    @if(!empty($cur_scheme->master_invoice_scheme_id))
                                        <span class="label label-info" title="@lang('superadmin::lang.shared_series_help')">@lang('superadmin::lang.shared_series')</span>
                                    @else
                                        <span class="label label-default">@lang('superadmin::lang.local_only')</span>
                                    @endif
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($cur_layout)
                                    {{ $cur_layout->name }}
                                    @if(!empty($cur_layout->master_invoice_layout_id))
                                        <span class="label label-info">@lang('superadmin::lang.shared_series')</span>
                                    @endif
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endcomponent

</section>

{{-- Assign modal --}}
<div class="modal fade" id="assign_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        {!! Form::open(['url' => action([\Modules\Superadmin\Http\Controllers\InvoiceAssignmentController::class, 'assign']), 'method' => 'post']) !!}
        {!! Form::hidden('master_scheme_id', null, ['id' => 'assign_scheme_id']) !!}
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">@lang('superadmin::lang.assign_to_stores'): <span id="assign_scheme_name"></span></h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    {!! Form::label('master_layout_id', __('superadmin::lang.master_layout') . ':*') !!}
                    {!! Form::select('master_layout_id', $master_layouts->pluck('name', 'id'), $master_layouts->where('is_default', 1)->first()->id ?? null, ['class' => 'form-control', 'required']) !!}
                </div>
                <div class="form-group">
                    {!! Form::label('mode', __('superadmin::lang.assign_mode') . ':') !!}
                    <div class="radio">
                        <label>
                            {!! Form::radio('mode', 'matching_gst', true) !!}
                            @lang('superadmin::lang.assign_all_matching_gst') (<span id="assign_scheme_gst"></span>)
                        </label>
                    </div>
                    <div class="radio">
                        <label>
                            {!! Form::radio('mode', 'selected', false) !!}
                            @lang('superadmin::lang.assign_selected_stores')
                        </label>
                    </div>
                </div>
                <div class="form-group" id="business_select_group" style="display:none;">
                    {!! Form::label('business_ids', __('superadmin::lang.select_stores') . ':') !!}
                    {!! Form::select('business_ids[]', $businesses->pluck('name', 'id'), null, ['class' => 'form-control select2', 'multiple', 'style' => 'width:100%']) !!}
                </div>
                <div class="alert alert-warning">
                    <i class="fa fa-exclamation-triangle"></i> @lang('superadmin::lang.assign_warning')
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">@lang('superadmin::lang.assign_to_stores')</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.cancel')</button>
            </div>
        </div>
        {!! Form::close() !!}
    </div>
</div>

{{-- Reset series modal --}}
<div class="modal fade" id="reset_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        {!! Form::open(['url' => action([\Modules\Superadmin\Http\Controllers\InvoiceAssignmentController::class, 'resetSeries']), 'method' => 'post']) !!}
        {!! Form::hidden('scheme_id', null, ['id' => 'reset_scheme_id']) !!}
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">@lang('superadmin::lang.start_new_series'): <span id="reset_scheme_name"></span></h4>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="fa fa-exclamation-triangle"></i> @lang('superadmin::lang.reset_warning')
                </div>
                <div class="form-group">
                    {!! Form::label('prefix', __('superadmin::lang.new_prefix') . ':') !!}
                    {!! Form::text('prefix', null, ['class' => 'form-control', 'id' => 'reset_prefix', 'placeholder' => 'e.g. GJ/25-26/']) !!}
                </div>
                <div class="form-group">
                    {!! Form::label('start_number', __('superadmin::lang.start_number') . ':') !!}
                    {!! Form::number('start_number', 1, ['class' => 'form-control', 'min' => 1]) !!}
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-warning">@lang('superadmin::lang.start_new_series')</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.cancel')</button>
            </div>
        </div>
        {!! Form::close() !!}
    </div>
</div>

@endsection

@section('javascript')
<script type="text/javascript">
    $(document).ready(function () {
        // Table filtering
        $('#search_schemes').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('#schemes_table tbody tr').filter(function() {
                // exclude form elements value from filter, or just use text
                var rowText = $(this).text().toLowerCase();
                var inputsText = $(this).find('input[type="text"]').map(function(){ return $(this).val(); }).get().join(' ').toLowerCase();
                var combinedText = rowText + ' ' + inputsText;
                $(this).toggle(combinedText.indexOf(value) > -1);
            });
        });

        $('#search_stores').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('#stores_table tbody tr').filter(function() {
                var rowText = $(this).text().toLowerCase();
                var inputsText = $(this).find('input[type="text"]').map(function(){ return $(this).val(); }).get().join(' ').toLowerCase();
                var combinedText = rowText + ' ' + inputsText;
                $(this).toggle(combinedText.indexOf(value) > -1);
            });
        });

        $(document).on('click', '.assign-btn', function () {
            $('#assign_scheme_id').val($(this).data('scheme_id'));
            $('#assign_scheme_name').text($(this).data('scheme_name'));
            $('#assign_scheme_gst').text($(this).data('gst') || '@lang('superadmin::lang.no_gst_set')');
            $('#assign_modal').modal('show');
        });

        $(document).on('click', '.reset-btn', function () {
            $('#reset_scheme_id').val($(this).data('scheme_id'));
            $('#reset_scheme_name').text($(this).data('scheme_name'));
            $('#reset_prefix').val($(this).data('prefix'));
            $('#reset_modal').modal('show');
        });

        $(document).on('change', 'input[name="mode"]', function () {
            $('#business_select_group').toggle($(this).val() == 'selected');
        });
    });
</script>
@endsection
