@extends('layouts.supplier')
@section('title', $po->ref_no)

@section('content')
    @php
        $status_bg = ['ordered'=>'bg-aqua','packed'=>'bg-yellow','shipped'=>'bg-teal','delivered'=>'bg-green','cancelled'=>'bg-red'];
        $ss = $po->shipping_status ?: 'ordered';
    @endphp

    <section class="content-header">
        <h1>@lang('purchase.purchase_order') <small>{{ $po->ref_no }}</small></h1>
        <ol class="breadcrumb">
            <li><a href="{{ route('supplier.purchase-orders') }}"><i class="fa fa-arrow-left"></i> @lang('messages.back')</a></li>
        </ol>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-8">
                <div class="box box-solid">
                    <div class="box-header with-border"><h3 class="box-title">@lang('purchase.purchase_order')</h3></div>
                    <div class="box-body">
                        <div class="row">
                            <div class="col-sm-6">
                                <p><strong>@lang('business.store'):</strong> {{ optional($po->business)->name }}</p>
                                <p><strong>@lang('purchase.business_locations'):</strong> {{ optional($po->location)->name }}</p>
                                <p><strong>@lang('business.address'):</strong>
                                   {{ optional($po->location)->landmark }} {{ optional($po->location)->city }} {{ optional($po->location)->state }} {{ optional($po->location)->zip_code }}</p>
                            </div>
                            <div class="col-sm-6">
                                <p><strong>@lang('purchase.ref_no'):</strong> {{ $po->ref_no }}</p>
                                <p><strong>@lang('messages.date'):</strong> {{ \Carbon\Carbon::parse($po->transaction_date)->format('d-m-Y H:i') }}</p>
                                <p><strong>@lang('lang_v1.dispatch_status'):</strong> <span class="label {{ $status_bg[$ss] ?? 'bg-gray' }}">{{ $shipping_statuses[$ss] ?? $ss }}</span></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="box box-solid">
                    <div class="box-header with-border"><h3 class="box-title">@lang('sale.products')</h3></div>
                    <div class="box-body table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>@lang('sale.product')</th>
                                    <th>@lang('sale.qty')</th>
                                    <th>@lang('lang_v1.unit_purchase_price')</th>
                                    <th>@lang('sale.subtotal')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($po->purchase_lines as $i => $line)
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>
                                            {{ optional($line->product)->name }}
                                            @if(optional($line->variations)->name && $line->variations->name !== 'DUMMY')({{ $line->variations->name }})@endif
                                        </td>
                                        <td>@format_quantity($line->quantity)</td>
                                        <td>@format_currency($line->purchase_price_inc_tax)</td>
                                        <td>@format_currency($line->quantity * $line->purchase_price_inc_tax)</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="4" class="text-right">@lang('sale.total')</th>
                                    <th>@format_currency($po->final_total)</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="box box-solid">
                    <div class="box-header with-border"><h3 class="box-title">@lang('lang_v1.update') @lang('lang_v1.dispatch_status')</h3></div>
                    <div class="box-body">
                        @if($can_update)
                            <form method="POST" action="{{ route('supplier.purchase-order.status', [$po->id]) }}">
                                @csrf
                                @method('PUT')
                                <div class="form-group">
                                    <label>@lang('lang_v1.dispatch_status')</label>
                                    <select name="shipping_status" class="form-control" required>
                                        @foreach($shipping_statuses as $key => $label)
                                            <option value="{{ $key }}" {{ $ss === $key ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>@lang('lang_v1.shipping_details')</label>
                                    <textarea name="shipping_details" class="form-control" rows="3" placeholder="@lang('lang_v1.shipping_details')">{{ $po->shipping_details }}</textarea>
                                </div>
                                <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-check"></i> @lang('messages.update')</button>
                            </form>
                        @else
                            <p class="text-muted">@lang('lang_v1.unauthorized')</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
