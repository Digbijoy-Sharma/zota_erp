@php
	$show_lot = session('business.enable_lot_number');
	$show_exp = session('business.enable_product_expiry');
	$status_colors = ['received' => ['#e7f5f0','#0f6e6e'], 'pending' => ['#fff3e0','#a6650f'], 'ordered' => ['#fff3e0','#a6650f']];
	$status_color = $status_colors[$purchase->status][0] ?? '#eef1f2';
	$status_text_color = $status_colors[$purchase->status][1] ?? '#5b6b70';

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
<style>
	#grn_print_root, #grn_print_root *{ box-sizing:border-box; }
	#grn_print_root{
		font-family: -apple-system, "Segoe UI", Helvetica, Arial, sans-serif;
		color:#1c2a30;
		background:#ffffff;
		max-width:900px;
		margin:0 auto;
		padding:28px 32px;
	}
	#grn_print_root .doc-header{
		display:flex;
		justify-content:space-between;
		align-items:flex-start;
		gap:24px;
		border-bottom:2.5px solid #1c2a30;
		padding-bottom:14px;
		margin-bottom:16px;
	}
	#grn_print_root .biz-name{ font-size:19px; font-weight:700; }
	#grn_print_root .biz-meta{ color:#5b6b70; font-size:12px; line-height:1.55; margin-top:4px; max-width:34ch; }
	#grn_print_root .doc-title-block{ text-align:right; }
	#grn_print_root .doc-title{ font-size:20px; font-weight:800; letter-spacing:.04em; color:#0f6e6e; text-transform:uppercase; }
	#grn_print_root .doc-sub{ font-size:11.5px; color:#5b6b70; letter-spacing:.03em; margin-top:2px; }
	#grn_print_root .status-pill{
		display:inline-block; margin-top:8px; font-size:10.5px; font-weight:700; letter-spacing:.06em;
		text-transform:uppercase; padding:3px 9px; border-radius:20px;
		background: {{ $status_color }}; color: {{ $status_text_color }}; border:1px solid {{ $status_text_color }};
	}
	#grn_print_root .meta-grid{
		display:grid; grid-template-columns:repeat(4,1fr); gap:14px 18px;
		margin-bottom:18px; padding-bottom:16px; border-bottom:1px solid #d8dee0;
	}
	#grn_print_root .meta-grid .field label{
		display:block; font-size:10px; letter-spacing:.08em; text-transform:uppercase; color:#5b6b70; margin-bottom:3px;
	}
	#grn_print_root .meta-grid .field .val{ font-size:13.5px; font-weight:600; }
	#grn_print_root .party-grid{
		display:grid; grid-template-columns:1fr 1fr; margin-bottom:20px;
		border:1px solid #d8dee0; border-radius:2px; overflow:hidden;
	}
	#grn_print_root .party{ padding:12px 16px; }
	#grn_print_root .party + .party{ border-left:1px solid #d8dee0; }
	#grn_print_root .party .party-label{
		font-size:10px; letter-spacing:.08em; text-transform:uppercase; color:#0f6e6e; font-weight:700; margin-bottom:5px;
	}
	#grn_print_root .party .party-name{ font-size:14px; font-weight:700; margin-bottom:2px; }
	#grn_print_root .party .party-detail{ font-size:12.5px; color:#5b6b70; line-height:1.6; }
	#grn_print_root table.items{ width:100%; border-collapse:collapse; margin-bottom:4px; font-size:12.5px; }
	#grn_print_root table.items thead th{
		text-align:left; font-size:10px; letter-spacing:.06em; text-transform:uppercase;
		color:#ffffff; background:#1c2a30; padding:7px 8px; white-space:nowrap;
	}
	#grn_print_root table.items thead th.num, #grn_print_root table.items tbody td.num{ text-align:right; }
	#grn_print_root table.items tbody td{ padding:6px 8px; border-bottom:1px solid #d8dee0; vertical-align:top; }
	#grn_print_root table.items tbody tr:nth-child(even){ background:#eef5f4; }
	#grn_print_root table.items tbody .desc{ font-weight:600; }
	#grn_print_root table.items tbody .sub{ display:block; font-weight:400; color:#5b6b70; font-size:11px; }
	#grn_print_root .below-table{ display:flex; justify-content:space-between; gap:24px; margin-top:12px; }
	#grn_print_root .notes-block{ flex:1 1 auto; max-width:360px; }
	#grn_print_root .notes-block .label{ font-size:10px; letter-spacing:.08em; text-transform:uppercase; color:#5b6b70; margin-bottom:5px; }
	#grn_print_root .notes-block .box{
		border:1px solid #d8dee0; border-radius:2px; padding:10px 12px; font-size:12px; min-height:44px; line-height:1.55;
	}
	#grn_print_root .totals{ width:270px; flex:0 0 auto; font-size:12.5px; }
	#grn_print_root .totals .row{ display:flex; justify-content:space-between; padding:4px 2px; }
	#grn_print_root .totals .row .lbl{ color:#5b6b70; }
	#grn_print_root .totals .row.grand{ border-top:2px solid #1c2a30; margin-top:4px; padding-top:8px; font-size:15px; font-weight:800; }
	#grn_print_root .totals .in-words{ margin-top:10px; font-size:11px; color:#5b6b70; font-style:italic; border-top:1px dashed #d8dee0; padding-top:8px; }
	#grn_print_root .sign-grid{ display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-top:36px; }
	#grn_print_root .sign-grid .slot{ border-top:1px solid #1c2a30; padding-top:6px; font-size:11px; color:#5b6b70; }
	#grn_print_root .sign-grid .slot strong{ display:block; color:#1c2a30; font-size:11.5px; font-weight:700; margin-bottom:1px; }
	#grn_print_root .doc-footer{
		display:flex; justify-content:space-between; align-items:center; margin-top:22px; padding-top:12px;
		border-top:1px solid #d8dee0; font-size:10.5px; color:#5b6b70;
	}
	@media (max-width:640px){
		#grn_print_root .meta-grid{ grid-template-columns:repeat(2,1fr); }
		#grn_print_root .party-grid{ grid-template-columns:1fr; }
		#grn_print_root .party + .party{ border-left:none; border-top:1px solid #d8dee0; }
		#grn_print_root .below-table{ flex-direction:column; }
		#grn_print_root .totals{ width:100%; }
		#grn_print_root .sign-grid{ grid-template-columns:repeat(2,1fr); }
	}
</style>

<div id="grn_print_root">

	<div class="doc-header">
		<div class="biz-block">
			<div class="biz-name">{{ $purchase->business->name }}</div>
			<div class="biz-meta">
				{{ $purchase->location->name }}
				@if(!empty($purchase->location->landmark)) <br>{{ $purchase->location->landmark }} @endif
				@if(!empty($purchase->location->city) || !empty($purchase->location->state) || !empty($purchase->location->country))
					<br>{{ implode(', ', array_filter([$purchase->location->city, $purchase->location->state, $purchase->location->country])) }}
				@endif
				@if(!empty($purchase->business->tax_number_1))
					<br>{{ $purchase->business->tax_label_1 }}: {{ $purchase->business->tax_number_1 }}
				@endif
				@if(!empty($purchase->location->mobile))
					<br>{{ $purchase->location->mobile }}
				@endif
			</div>
		</div>
		<div class="doc-title-block">
			<div class="doc-title">@lang('purchase.grn_title')</div>
			<div class="doc-sub">@lang('purchase.grn')</div>
			@if(!empty($purchase->status))
				<div class="status-pill">{{ __('lang_v1.' . $purchase->status) }}</div>
			@endif
		</div>
	</div>

	<div class="meta-grid">
		<div class="field"><label>@lang('purchase.grn_no')</label><div class="val">{{ $purchase->ref_no }}</div></div>
		<div class="field"><label>@lang('messages.date')</label><div class="val">{{ @format_date($purchase->transaction_date) }}</div></div>
		<div class="field"><label>@lang('restaurant.order_no')</label><div class="val">{{ $purchase_order_nos ?: '--' }}</div></div>
		<div class="field"><label>@lang('lang_v1.order_dates')</label><div class="val">{{ $purchase_order_dates ?: '--' }}</div></div>
		<div class="field"><label>@lang('purchase.purchase_status')</label><div class="val">{{ !empty($purchase->status) ? __('lang_v1.' . $purchase->status) : '--' }}</div></div>
		<div class="field"><label>@lang('purchase.payment_status')</label><div class="val">{{ !empty($purchase->payment_status) ? __('lang_v1.' . $purchase->payment_status) : '--' }}</div></div>
		<div class="field"><label>@lang('purchase.payment_terms')</label><div class="val">{{ $pay_term ?: '--' }}</div></div>
		<div class="field"><label>@lang('purchase.business_location')</label><div class="val">{{ $purchase->location->name }}</div></div>
	</div>

	<div class="party-grid">
		<div class="party">
			<div class="party-label">@lang('purchase.supplier')</div>
			<div class="party-name">{{ $purchase->contact->name }}</div>
			<div class="party-detail">
				{!! $purchase->contact->contact_address !!}
				@if(!empty($purchase->contact->tax_number))<br>@lang('contact.tax_no'): {{ $purchase->contact->tax_number }}@endif
				@if(!empty($purchase->contact->mobile))<br>@lang('contact.mobile'): {{ $purchase->contact->mobile }}@endif
				@if(!empty($purchase->contact->email))<br>@lang('business.email'): {{ $purchase->contact->email }}@endif
			</div>
		</div>
		<div class="party">
			<div class="party-label">@lang('purchase.received_by')</div>
			<div class="party-name">{{ $purchase->location->name }}</div>
			<div class="party-detail">
				{!! $purchase->location->location_address !!}
				@if(!empty($purchase->sales_person))<br>{{ __('purchase.received_by') }}: <strong>{{ $purchase->sales_person->user_full_name }}</strong>@endif
				<br>{{ __('messages.date') }}: {{ @format_datetime($purchase->created_at) }}
			</div>
		</div>
	</div>

	<table class="items">
		<thead>
			<tr>
				<th style="width:26px;">#</th>
				<th>@lang('product.product_name')</th>
				@if($show_lot || $show_exp)
					<th>@lang('lang_v1.lot_number') / @lang('product.exp_date')</th>
				@endif
				<th class="num">@lang('purchase.purchase_quantity')</th>
				<th class="num">@lang('purchase.unit_cost_after_tax')</th>
				<th class="num">@lang('sale.tax')</th>
				<th class="num">@lang('purchase.line_total')</th>
			</tr>
		</thead>
		<tbody>
			@foreach($purchase->purchase_lines as $purchase_line)
				<tr>
					<td>{{ $loop->iteration }}</td>
					<td>
						<span class="desc">
							{{ $purchase_line->product->name }}
							@if($purchase_line->product->type == 'variable')
								- {{ $purchase_line->variations->product_variation->name ?? '' }} - {{ $purchase_line->variations->name ?? '' }}
							@endif
						</span>
						<span class="sub">
							@lang('product.sku'):
							{{ $purchase_line->product->type == 'variable' ? ($purchase_line->variations->sub_sku ?? '') : $purchase_line->product->sku }}
						</span>
						@if($show_damage_loss && ($purchase_line->quantity_damaged || $purchase_line->quantity_lost))
							<span class="sub" style="color:#b3521f;">
								&#9888;
								@if($purchase_line->quantity_damaged) {{ @format_quantity($purchase_line->quantity_damaged) }} @lang('purchase.quantity_damaged') @endif
								@if($purchase_line->quantity_lost) {{ @format_quantity($purchase_line->quantity_lost) }} @lang('purchase.quantity_lost') @endif
								@if(!empty($purchase_line->damage_loss_reason)) &mdash; {{ __('lang_v1.' . $purchase_line->damage_loss_reason) }} @endif
							</span>
						@endif
					</td>
					@if($show_lot || $show_exp)
						<td>
							@if($show_lot && !empty($purchase_line->lot_number)) {{ __('lang_v1.lot_number') }}: {{ $purchase_line->lot_number }} @endif
							@if($show_exp && !empty($purchase_line->exp_date))
								<span class="sub">@lang('product.exp_date'): {{ @format_date($purchase_line->exp_date) }}</span>
							@endif
						</td>
					@endif
					<td class="num">
						{{ @format_quantity($purchase_line->quantity) }}
						{{ !empty($purchase_line->sub_unit) ? $purchase_line->sub_unit->actual_name : $purchase_line->product->unit->actual_name }}
					</td>
					<td class="num">@format_currency($purchase_line->purchase_price_inc_tax)</td>
					<td class="num">@format_currency($purchase_line->item_tax)</td>
					<td class="num">@format_currency($purchase_line->purchase_price_inc_tax * $purchase_line->quantity)</td>
				</tr>
			@endforeach
		</tbody>
	</table>

	<div class="below-table">
		<div class="notes-block">
			<div class="label">@lang('purchase.additional_notes')</div>
			<div class="box">{{ $purchase->additional_notes ?: '--' }}</div>
		</div>
		<div class="totals">
			<div class="row"><span class="lbl">@lang('purchase.net_total_amount')</span><span>@format_currency($net_total)</span></div>
			@if(!empty($purchase->discount_amount))
				<div class="row"><span class="lbl">@lang('purchase.discount')</span>
					<span>
						@if($purchase->discount_type == 'percentage')
							-@format_currency($purchase->discount_amount * $net_total / 100) ({{ $purchase->discount_amount }}%)
						@else
							-@format_currency($purchase->discount_amount)
						@endif
					</span>
				</div>
			@endif
			@if(!empty($purchase_taxes))
				@foreach($purchase_taxes as $tax_name => $tax_amount)
					<div class="row"><span class="lbl">{{ $tax_name }}</span><span>@format_currency($tax_amount)</span></div>
				@endforeach
			@endif
			@if(!empty($purchase->shipping_charges))
				<div class="row"><span class="lbl">@lang('purchase.additional_shipping_charges')</span><span>@format_currency($purchase->shipping_charges)</span></div>
			@endif
			@if($show_damage_loss && $damage_loss_value > 0)
				<div class="row"><span class="lbl" style="color:#b3521f;">@lang('purchase.damage_loss_value')</span><span style="color:#b3521f;">@format_currency($damage_loss_value)</span></div>
			@endif
			<div class="row grand"><span>@lang('purchase.purchase_total')</span><span>@format_currency($purchase->final_total)</span></div>
		</div>
	</div>

	<div class="sign-grid">
		<div class="slot"><strong>@lang('purchase.received_by')</strong>{{ $purchase->sales_person->user_full_name ?? '' }}</div>
		<div class="slot"><strong>@lang('purchase.checked_by')</strong></div>
		<div class="slot"><strong>@lang('purchase.approved_by')</strong></div>
		<div class="slot"><strong>@lang('purchase.supplier')</strong></div>
	</div>

	<div class="doc-footer">
		<div>{{ $purchase->business->name }} &nbsp;&middot;&nbsp; @lang('purchase.printed_on'): {{ @format_datetime(now()) }}</div>
		<img src="data:image/png;base64,{{ DNS1D::getBarcodePNG($purchase->ref_no, 'C128', 1.6, 26, array(28, 42, 48), true) }}">
	</div>

</div>
