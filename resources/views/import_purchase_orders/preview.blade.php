@extends('layouts.app')
@section('title', __('lang_v1.preview_imported_purchase_orders'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.preview_imported_purchase_orders')</h1>
</section>

<!-- Main content -->
<section class="content">
    {!! Form::open(['url' => action([\App\Http\Controllers\ImportPurchaseOrdersController::class, 'import']), 'method' => 'post', 'id' => 'import_purchase_order_form']) !!}
    {!! Form::hidden('file_name', $file_name); !!}
    @component('components.widget')
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('location_id', __('business.business_location') . ':*') !!}
                {!! Form::select('location_id', $business_locations, null, ['class' => 'form-control', 'required', 'placeholder' => __('messages.please_select')]); !!}
            </div>
        </div>
    </div>
    @endcomponent
    @component('components.widget')
    <div class="row">
        <div class="col-md-12">
            <div class="scroll-top-bottom" style="max-height: 400px;">
                <table class="table table-condensed table-striped">
                    @foreach(array_slice($parsed_array, 0, 101) as $row)
                        <tr>
                            <td>@if($loop->index > 0 ){{$loop->index}} @else # @endif</td>
                            @foreach($row as $v)
                                @if($loop->parent->index == 0)
                                    <th>{{$v}}</th>
                                @else
                                    <td>{{$v}}</td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
    </div>
    @endcomponent
    <div class="row">
        <div class="col-md-12">
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white pull-right">@lang('messages.submit')</button>
        </div>
    </div>
    {!! Form::close() !!}
</section>
@stop
