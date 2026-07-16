@extends('layouts.app')
@section('title', __('superadmin::lang.manage_suppliers') . ' | ' . $business->name)

@section('content')
    @include('superadmin::layouts.nav')

    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
            @lang('superadmin::lang.manage_suppliers')
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">{{ $business->name }}</small>
        </h1>
        <a href="{{ action([\Modules\Superadmin\Http\Controllers\BusinessController::class, 'show'], [$business->id]) }}"
           class="tw-dw-btn tw-dw-btn-secondary tw-text-white tw-mt-2">
            <i class="fa fa-arrow-left"></i> @lang('messages.back')
        </a>
    </section>

    <section class="content">
        {!! Form::open(['url' => action([\Modules\Superadmin\Http\Controllers\BusinessController::class, 'syncSuppliers'], [$business->id]), 'method' => 'post', 'id' => 'sync_suppliers_form']) !!}
        <div class="box box-solid">
            <div class="box-header with-border">
                <h3 class="box-title">@lang('superadmin::lang.assign_common_suppliers')</h3>
            </div>
            <div class="box-body">
                @if (session('status'))
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        {{ session('status') }}
                    </div>
                @endif

                <p class="text-muted">@lang('superadmin::lang.assign_common_suppliers_help')</p>

                @if (empty($all_suppliers) || $all_suppliers->isEmpty())
                    <div class="alert alert-warning">
                        <i class="fa fa-info-circle"></i>
                        <strong>No suppliers available.</strong>
                        Please add suppliers in
                        <a href="{{ action([\App\Http\Controllers\ContactController::class, 'index']) }}" target="_blank">
                            Contacts &rarr; Suppliers
                        </a>
                        first. These suppliers (in the super admin's business) will appear here and can then be assigned to any business.
                    </div>
                @else
                    <div class="form-group">
                        {!! Form::label('supplier_ids', __('superadmin::lang.assign_common_suppliers') . ':') !!}
                        {!! Form::select('supplier_ids[]', $all_suppliers->pluck('display_name', 'id'), $assigned_ids, [
                            'class' => 'form-control select2',
                            'multiple' => 'multiple',
                            'id' => 'supplier_ids',
                        ]); !!}
                    </div>

                    <h4>@lang('superadmin::lang.currently_assigned') ({{ count($assigned_ids) }})</h4>
                    @if (count($assigned_ids) > 0)
                        <ul class="list-group">
                            @foreach ($all_suppliers->whereIn('id', $assigned_ids) as $assigned)
                                <li class="list-group-item">
                                    <strong>{{ $assigned->display_name }}</strong>
                                    @if ($assigned->email) <small class="text-muted">— {{ $assigned->email }}</small> @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted">@lang('superadmin::lang.no_suppliers_assigned')</p>
                    @endif
                @endif
            </div>
            <div class="box-footer">
                <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">
                    <i class="fa fa-save"></i> @lang('messages.save')
                </button>
            </div>
        </div>
        {!! Form::close() !!}
    </section>
@endsection

@section('javascript')
    <script type="text/javascript">
        (function ($) {
            $(document).ready(function () {
                $('#supplier_ids').select2({
                    width: '100%',
                    placeholder: '@lang("superadmin::lang.select_suppliers_placeholder")',
                    allowClear: true,
                });

                $('#sync_suppliers_form').on('submit', function (e) {
                    e.preventDefault();
                    var $form = $(this);
                    var $btn = $form.find('button[type="submit"]');
                    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
                    $.ajax({
                        method: $form.attr('method'),
                        url: $form.attr('action'),
                        data: $form.serialize(),
                        dataType: 'json',
                        success: function (result) {
                            if (result.success) {
                                toastr.success(result.msg);
                                setTimeout(function () { location.reload(); }, 600);
                            } else {
                                toastr.error(result.msg);
                                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> @lang("messages.save")');
                            }
                        },
                        error: function (xhr) {
                            var msg = '@lang("messages.something_went_wrong")';
                            try { var json = JSON.parse(xhr.responseText); if (json && json.msg) msg = json.msg; } catch (e) {}
                            toastr.error(msg);
                            $btn.prop('disabled', false).html('<i class="fa fa-save"></i> @lang("messages.save")');
                        }
                    });
                });
            });
        })(jQuery);
    </script>
@endsection
