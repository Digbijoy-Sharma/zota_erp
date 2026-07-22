@php
	$show_lot = session('business.enable_lot_number');
	$show_exp = session('business.enable_product_expiry');
	$total_cols = 5 + ($show_lot ? 1 : 0) + ($show_exp ? 1 : 0);

	$net_total = 0;
	$damage_loss_value = 0;
	$show_damage_loss = session('business.enable_damage_loss_tracking');
	foreach ($purchase->purchase_lines as $pl) {
		$net_total += $pl->quantity * $pl->purchase_price;
		$damage_loss_value += ($pl->quantity_damaged + $pl->quantity_lost) * $pl->purchase_price_inc_tax;
	}

	$pay_term = '';
	if (!empty($purchase->contact->pay_term_number) && !empty($purchase->contact->pay_term_type)) {
		$pay_term = $purchase->contact->pay_term_number . ' ' . ucfirst($purchase->contact->pay_term_type);
	}
@endphp
<style type="text/css">
	body { color: #1c2a30; }
	table.tpdf {
	  width: 100% !important;
	  border-collapse: collapse;
	  line-height: 1.25;
	  font-size: 10.5pt;
	}
	table.tpdf, table.tpdf tr, table.tpdf td, table.tpdf th {
	  border: 1px solid #d8dee0;
	  padding: 5px 8px;
	}
	.box { border: 1px solid #d8dee0; }
	.label-xs { font-size: 7.5pt; letter-spacing: 0.5px; text-transform: uppercase; color: #5b6b70; }
	.val { font-size: 10.5pt; font-weight: bold; color: #1c2a30; }
	.muted { color: #5b6b70; }
</style>

<table style="width:100%;border:none;border-bottom: 2px solid #1c2a30;margin-bottom:10px;">
	<tr>
		<td style="border:none;width:60%;vertical-align:top;padding:0 0 8px 0;">
			<strong style="font-size:14pt;">{{ $purchase->business->name }}</strong><br>
			<span class="muted">
				{{ $purchase->location->name }}
				@if(!empty($purchase->location->city) || !empty($purchase->location->state) || !empty($purchase->location->country))
					, {{ implode(', ', array_filter([$purchase->location->city, $purchase->location->state, $purchase->location->country])) }}
				@endif
				@if(!empty($purchase->business->tax_number_1))
					<br>{{ $purchase->business->tax_label_1 }}: {{ $purchase->business->tax_number_1 }}
				@endif
				@if(!empty($purchase->location->mobile))
					&nbsp;&middot;&nbsp;{{ $purchase->location->mobile }}
				@endif
			</span>
		</td>
		<td style="border:none;width:40%;text-align:right;vertical-align:top;padding:0 0 8px 0;">
			<strong style="font-size:13pt;letter-spacing:1px;color:#0f6e6e;">@lang('purchase.grn_title')</strong><br>
			<span class="muted">@lang('purchase.grn')</span><br>
			@if(!empty($purchase->status))
				<span style="display:inline-block;margin-top:4px;font-size:8pt;font-weight:bold;letter-spacing:0.5px;padding:2px 8px;border:1px solid #0f6e6e;color:#0f6e6e;">
					{{ strtoupper(__('lang_v1.' . $purchase->status)) }}
				</span>
			@endif
		</td>
	</tr>
</table>

<table class="tpdf" style="margin-bottom:10px;">
	<tr>
		<td style="width:25%;"><span class="label-xs">@lang('purchase.grn_no')</span><br><span class="val">{{ $purchase->ref_no }}</span></td>
		<td style="width:25%;"><span class="label-xs">@lang('messages.date')</span><br><span class="val">{{ @format_date($purchase->transaction_date) }}</span></td>
		<td style="width:25%;"><span class="label-xs">@lang('restaurant.order_no')</span><br><span class="val">{{ $purchase_order_nos ?: '--' }}</span></td>
		<td style="width:25%;"><span class="label-xs">@lang('lang_v1.order_dates')</span><br><span class="val">{{ $purchase_order_dates ?: '--' }}</span></td>
	</tr>
	<tr>
		<td><span class="label-xs">@lang('purchase.payment_status')</span><br><span class="val">{{ !empty($purchase->payment_status) ? __('lang_v1.' . $purchase->payment_status) : '--' }}</span></td>
		<td><span class="label-xs">@lang('purchase.payment_terms')</span><br><span class="val">{{ $pay_term ?: '--' }}</span></td>
		<td colspan="2"><span class="label-xs">@lang('purchase.business_location')</span><br><span class="val">{{ $purchase->location->name }}</span></td>
	</tr>
</table>

<table style="width:100%;border-collapse:collapse;margin-bottom:10px;">
	<tr>
		<td class="box" style="width:50%;vertical-align:top;padding:8px 12px;">
			<span class="label-xs" style="color:#0f6e6e;">@lang('purchase.supplier')</span><br>
			<strong>{{ $purchase->contact->name }}</strong><br>
			<span class="muted">
				{!! $purchase->contact->contact_address !!}
				@if(!empty($purchase->contact->tax_number))<br>@lang('contact.tax_no'): {{ $purchase->contact->tax_number }}@endif
				@if(!empty($purchase->contact->mobile))<br>@lang('contact.mobile'): {{ $purchase->contact->mobile }}@endif
			</span>
		</td>
		<td class="box" style="width:50%;vertical-align:top;padding:8px 12px;border-left:none;">
			<span class="label-xs" style="color:#0f6e6e;">@lang('purchase.received_by')</span><br>
			<strong>{{ $purchase->location->name }}</strong><br>
			<span class="muted">
				{!! $purchase->location->location_address !!}
				@if(!empty($purchase->sales_person))<br>{{ __('purchase.received_by') }}: {{ $purchase->sales_person->user_full_name }}@endif
				<br>{{ __('messages.date') }}: {{ @format_datetime($purchase->created_at) }}
			</span>
		</td>
	</tr>
</table>

<table class="tpdf">
	<thead>
		<tr>
			<th style="background:#1c2a30;color:#ffffff;">#</th>
			<th style="background:#1c2a30;color:#ffffff;width:30% !important;">@lang('product.product_name')</th>
			@if($show_lot)
				<th style="background:#1c2a30;color:#ffffff;">@lang('lang_v1.lot_number')</th>
			@endif
			@if($show_exp)
				<th style="background:#1c2a30;color:#ffffff;">@lang('product.exp_date')</th>
			@endif
			<th style="background:#1c2a30;color:#ffffff;text-align:right;">@lang('purchase.purchase_quantity')</th>
			<th style="background:#1c2a30;color:#ffffff;text-align:right;">@lang('purchase.unit_cost_after_tax')</th>
			<th style="background:#1c2a30;color:#ffffff;text-align:right;">@lang('purchase.line_total')</th>
		</tr>
	</thead>
	@php $tax_array = []; @endphp
	@foreach($purchase->purchase_lines as $purchase_line)
		<tr @if($loop->iteration % 2 == 0) style="background:#eef5f4;" @endif>
			<td>{{ $loop->iteration }}</td>
			<td style="width:30% !important;">
				{{ $purchase_line->product->name }}
				@if( $purchase_line->product->type == 'variable')
                  - {{ $purchase_line->variations->product_variation->name ?? '' }}
                  - {{ $purchase_line->variations->name ?? '' }}
                @endif
				<br><span class="label-xs">@lang('product.sku'): {{ $purchase_line->product->type == 'variable' ? ($purchase_line->variations->sub_sku ?? '') : $purchase_line->product->sku }}</span>
				@if($show_damage_loss && ($purchase_line->quantity_damaged || $purchase_line->quantity_lost))
					<br><span class="label-xs" style="color:#b3521f;">
						@if($purchase_line->quantity_damaged) {{ @format_quantity($purchase_line->quantity_damaged) }} @lang('purchase.quantity_damaged') @endif
						@if($purchase_line->quantity_lost) {{ @format_quantity($purchase_line->quantity_lost) }} @lang('purchase.quantity_lost') @endif
						@if(!empty($purchase_line->damage_loss_reason)) - {{ __('lang_v1.' . $purchase_line->damage_loss_reason) }} @endif
					</span>
				@endif
			</td>
			@if($show_lot)
				<td>{{ $purchase_line->lot_number }}</td>
			@endif
			@if($show_exp)
				<td>
					@if(!empty($purchase_line->exp_date))
						{{ @format_date($purchase_line->exp_date) }}
					@endif
				</td>
			@endif
			<td style="text-align:right;">
				{{@format_quantity($purchase_line->quantity)}} @if(!empty($purchase_line->sub_unit)) {{$purchase_line->sub_unit->actual_name}} @else {{$purchase_line->product->unit->actual_name}} @endif
			</td>
			<td style="text-align:right;">
				@format_currency($purchase_line->purchase_price_inc_tax)
			</td>
			<td style="text-align:right;">
				@php
					if (!empty($purchase_line->tax_id)) {
						$tax_array[$purchase_line->tax_id][] = ($purchase_line->item_tax * $purchase_line->quantity);
					}
				@endphp
				@format_currency($purchase_line->purchase_price_inc_tax * $purchase_line->quantity)
			</td>
		</tr>
	@endforeach
	<tr>
		<td colspan="{{ $total_cols - 2 }}" style="vertical-align:top;">
			<span class="label-xs">@lang('purchase.additional_notes')</span><br>
			{{ $purchase->additional_notes ?: '--' }}
		</td>
		<td colspan="2" style="vertical-align:top;">
			@lang('purchase.net_total_amount'): @format_currency($net_total) <br>
			@if(!empty($purchase->discount_amount))
				@lang('purchase.discount'):
				@if($purchase->discount_type == 'percentage')
					-@format_currency($purchase->discount_amount * $net_total / 100) ({{ $purchase->discount_amount }}%)
				@else
					-@format_currency($purchase->discount_amount)
				@endif
				<br>
			@endif
			@if(!empty($tax_array))
				@foreach($tax_array as $key => $value)
					{{ $taxes->where('id', $key)->first()->name ?? '' }}: @format_currency(array_sum($value)) <br>
				@endforeach
			@endif
			@if(!empty($purchase->shipping_charges))
				@lang('purchase.additional_shipping_charges'): @format_currency($purchase->shipping_charges) <br>
			@endif
			@if($show_damage_loss && $damage_loss_value > 0)
				<span style="color:#b3521f;">@lang('purchase.damage_loss_value'): @format_currency($damage_loss_value)</span><br>
			@endif
			<strong>@lang('purchase.purchase_total'): @format_currency($purchase->final_total)</strong>
		</td>
	</tr>
	<tr>
		<td colspan="{{ $total_cols }}"><em>{!!ucfirst($total_in_words)!!}</em></td>
	</tr>
</table>

<table style="width:100%;border-collapse:collapse;margin-top:26px;">
	<tr>
		<td style="border:none;border-top:1px solid #1c2a30;padding-top:6px;font-size:9pt;">
			<strong>@lang('purchase.received_by')</strong><br><span class="muted">{{ $purchase->sales_person->user_full_name ?? '' }}</span>
		</td>
		<td style="border:none;border-top:1px solid #1c2a30;padding-top:6px;font-size:9pt;">
			<strong>@lang('purchase.checked_by')</strong>
		</td>
		<td style="border:none;border-top:1px solid #1c2a30;padding-top:6px;font-size:9pt;">
			<strong>@lang('purchase.approved_by')</strong>
		</td>
		<td style="border:none;border-top:1px solid #1c2a30;padding-top:6px;font-size:9pt;">
			<strong>@lang('purchase.supplier')</strong>
		</td>
	</tr>
</table>

<table style="width:100%;border-collapse:collapse;margin-top:16px;">
	<tr>
		<td style="border:none;font-size:8pt;color:#5b6b70;vertical-align:middle;">
			{{ $purchase->business->name }} &middot; @lang('purchase.printed_on'): {{ @format_datetime(\Carbon\Carbon::now()) }}
		</td>
		<td style="border:none;text-align:right;">
			<img src="data:image/png;base64,{{DNS1D::getBarcodePNG($purchase->ref_no, 'C128', 1.6, 26, array(28, 42, 48), true)}}">
		</td>
	</tr>
</table>
