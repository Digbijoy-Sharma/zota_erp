@extends('layouts.supplier')
@section('title', __('lang_v1.purchase_order'))

@section('content')
    @php
        $status_bg = ['ordered'=>'bg-aqua','packed'=>'bg-yellow','shipped'=>'bg-teal','delivered'=>'bg-green','cancelled'=>'bg-red'];
    @endphp

    <section class="content-header">
        <h1>@lang('lang_v1.purchase_order') <small>@lang('superadmin::lang.all_business')</small></h1>
    </section>

    <section class="content">
        <div class="box box-solid">
            <div class="box-header with-border"><h3 class="box-title">@lang('report.filters')</h3></div>
            <div class="box-body">
                <form method="GET" action="{{ route('supplier.purchase-orders') }}" class="form-inline">
                    <div class="form-group">
                        <label>@lang('lang_v1.dispatch_status'):</label>
                        <select name="shipping_status" class="form-control">
                            <option value="">@lang('lang_v1.all')</option>
                            @foreach($shipping_statuses as $key => $label)
                                <option value="{{ $key }}" {{ $filter_status === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-left:8px;">
                        <input type="text" name="ref_no" value="{{ request('ref_no') }}" class="form-control" placeholder="@lang('purchase.ref_no')">
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-left:8px;"><i class="fa fa-search"></i> @lang('messages.search')</button>
                </form>
            </div>
        </div>

        <div class="box box-solid">
            <div class="box-header with-border"><h3 class="box-title">@lang('lang_v1.all') @lang('lang_v1.purchase_order')</h3></div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>@lang('purchase.ref_no')</th>
                            <th>@lang('business.store')</th>
                            <th>@lang('purchase.business_locations')</th>
                            <th>@lang('messages.date')</th>
                            <th>@lang('lang_v1.delivery_date')</th>
                            <th>@lang('sale.total')</th>
                            <th>@lang('lang_v1.dispatch_status')</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($purchase_orders as $po)
                            @php $ss = $po->shipping_status ?: 'ordered'; @endphp
                            <tr>
                                <td>{{ $po->ref_no }}</td>
                                <td>{{ optional($po->business)->name }}</td>
                                <td>{{ optional($po->location)->name }}</td>
                                <td>{{ \Carbon\Carbon::parse($po->transaction_date)->format('d-m-Y') }}</td>
                                <td>{{ $po->delivery_date ? \Carbon\Carbon::parse($po->delivery_date)->format('d-m-Y') : '-' }}</td>
                                <td>@format_currency($po->final_total)</td>
                                <td><span class="label {{ $status_bg[$ss] ?? 'bg-gray' }}">{{ $shipping_statuses[$ss] ?? $ss }}</span></td>
                                <td><a href="{{ route('supplier.purchase-order.show', [$po->id]) }}" class="btn btn-xs btn-info"><i class="fa fa-eye"></i> @lang('messages.view')</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted">@lang('lang_v1.no_records_found')</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div>{{ $purchase_orders->links() }}</div>
            </div>
        </div>
    </section>
@endsection
