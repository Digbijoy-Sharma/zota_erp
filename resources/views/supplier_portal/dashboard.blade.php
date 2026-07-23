@extends('layouts.supplier')
@section('title', __('home.dashboard'))

@section('content')
    @php
        $status_meta = [
            'ordered'   => ['bg-aqua',   'fa-hourglass-half'],
            'packed'    => ['bg-yellow', 'fa-box'],
            'shipped'   => ['bg-teal',   'fa-truck'],
            'delivered' => ['bg-green',  'fa-check'],
            'cancelled' => ['bg-red',    'fa-ban'],
        ];
    @endphp

    <section class="content-header">
        <h1>@lang('home.dashboard') <small>@lang('lang_v1.supplier_portal')</small></h1>
    </section>

    <section class="content">
        {{-- Headline stats --}}
        <div class="row">
            <div class="col-lg-4 col-xs-12">
                <div class="small-box bg-blue">
                    <div class="inner"><h3>{{ $total_pos }}</h3><p>@lang('lang_v1.purchase_order')</p></div>
                    <div class="icon"><i class="fa fa-file-invoice"></i></div>
                </div>
            </div>
            <div class="col-lg-4 col-xs-12">
                <div class="small-box bg-green">
                    <div class="inner"><h3>{{ $store_count }}</h3><p>@lang('business.store')</p></div>
                    <div class="icon"><i class="fa fa-store"></i></div>
                </div>
            </div>
            <div class="col-lg-4 col-xs-12">
                <div class="small-box bg-purple">
                    <div class="inner"><h3 style="font-size:26px;">@format_currency($total_value)</h3><p>@lang('sale.total') @lang('lang_v1.value')</p></div>
                    <div class="icon"><i class="fa fa-rupee-sign"></i></div>
                </div>
            </div>
        </div>

        {{-- Dispatch status breakdown --}}
        <div class="box box-solid">
            <div class="box-header with-border"><h3 class="box-title">@lang('lang_v1.dispatch_status')</h3></div>
            <div class="box-body">
                <div class="row">
                    @foreach($shipping_statuses as $key => $label)
                        @php [$bg, $icon] = $status_meta[$key] ?? ['bg-gray', 'fa-circle']; @endphp
                        <div class="col-md-2 col-sm-4 col-xs-6">
                            <div class="small-box {{ $bg }}">
                                <div class="inner"><h3>{{ $status_counts[$key] ?? 0 }}</h3><p>{{ $label }}</p></div>
                                <div class="icon"><i class="fa {{ $icon }}"></i></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Recent POs --}}
        <div class="box box-solid">
            <div class="box-header with-border">
                <h3 class="box-title">@lang('lang_v1.recent') @lang('lang_v1.purchase_order')</h3>
                <div class="box-tools">
                    <a href="{{ route('supplier.purchase-orders') }}" class="btn btn-primary btn-sm">@lang('messages.view') @lang('lang_v1.all')</a>
                </div>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>@lang('purchase.ref_no')</th>
                            <th>@lang('business.store')</th>
                            <th>@lang('messages.date')</th>
                            <th>@lang('sale.total')</th>
                            <th>@lang('lang_v1.dispatch_status')</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recent_pos as $po)
                            @php $ss = $po->shipping_status ?: 'ordered'; @endphp
                            <tr>
                                <td>{{ $po->ref_no }}</td>
                                <td>{{ optional($po->business)->name }}</td>
                                <td>{{ \Carbon\Carbon::parse($po->transaction_date)->format('d-m-Y') }}</td>
                                <td>@format_currency($po->final_total)</td>
                                <td><span class="label {{ ($status_meta[$ss][0] ?? 'bg-gray') }}">{{ $shipping_statuses[$ss] ?? $ss }}</span></td>
                                <td><a href="{{ route('supplier.purchase-order.show', [$po->id]) }}" class="btn btn-xs btn-info"><i class="fa fa-eye"></i> @lang('messages.view')</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">@lang('lang_v1.no_records_found')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
