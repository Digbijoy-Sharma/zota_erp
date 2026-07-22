@foreach( $variations as $variation)
    <tr data-row-index="{{ $row_count }}" @if(!empty($purchase_order_line)) data-purchase_order_id="{{$purchase_order_line->transaction_id}}" @endif @if(!empty($purchase_requisition_line)) data-purchase_requisition_id="{{$purchase_requisition_line->transaction_id}}" @endif>
        <td><span class="sr_number"></span></td>
        <td>
            {{ $product->name }} ({{$variation->sub_sku}})
            @if( $product->type == 'variable' )
                <br/>
                (<b>{{ $variation->product_variation->name }}</b> : {{ $variation->name }})
            @endif
            @if($product->enable_stock == 1)
                <br>
                <small class="text-muted" style="white-space: nowrap;">@lang('report.current_stock'): @if(!empty($variation->variation_location_details->first())) {{@num_format($variation->variation_location_details->first()->qty_available)}} @else 0 @endif {{ $product->unit->short_name }}</small>
            @endif
            
        </td>
        <td>
            @if(!empty($purchase_order_line))
                {!! Form::hidden('purchases[' . $row_count . '][purchase_order_line_id]', $purchase_order_line->id ); !!}
            @endif

            @if(!empty($purchase_requisition_line))
                {!! Form::hidden('purchases[' . $row_count . '][purchase_requisition_line_id]', $purchase_requisition_line->id ); !!}
            @endif

            {!! Form::hidden('purchases[' . $row_count . '][product_id]', $product->id ); !!}
            {!! Form::hidden('purchases[' . $row_count . '][variation_id]', $variation->id , ['class' => 'hidden_variation_id']); !!}

            @php
                $check_decimal = 'false';
                if($product->unit->allow_decimal == 0){
                    $check_decimal = 'true';
                }
                $currency_precision = session('business.currency_precision', 2);
                $quantity_precision = session('business.quantity_precision', 2);

                $quantity_value = !empty($purchase_order_line) ? $purchase_order_line->quantity : 1;

                $quantity_value = !empty($purchase_requisition_line) ? $purchase_requisition_line->quantity - $purchase_requisition_line->po_quantity_purchased : $quantity_value;
                $max_quantity = !empty($purchase_order_line) ? $purchase_order_line->quantity - $purchase_order_line->po_quantity_purchased : 0;

                $max_quantity = !empty($purchase_requisition_line) ? $purchase_requisition_line->quantity - $purchase_requisition_line->po_quantity_purchased : $max_quantity;

                $quantity_value = !empty($imported_data) ? $imported_data['quantity'] : $quantity_value;
            @endphp
            
            <input type="text" 
                name="purchases[{{$row_count}}][quantity]" 
                value="{{@format_quantity($quantity_value)}}"
                class="form-control input-sm purchase_quantity input_number mousetrap"
                required
                data-rule-abs_digit={{$check_decimal}}
                data-msg-abs_digit="{{__('lang_v1.decimal_value_not_allowed')}}"
                @if(!empty($max_quantity))
                    data-rule-max-value="{{$max_quantity}}"
                    data-msg-max-value="{{__('lang_v1.max_quantity_quantity_allowed', ['quantity' => $max_quantity])}}" 
                @endif
            >


            <input type="hidden" class="base_unit_cost" value="{{$variation->default_purchase_price}}">
            <input type="hidden" class="base_unit_selling_price" value="{{$variation->sell_price_inc_tax}}">

            <input type="hidden" name="purchases[{{$row_count}}][product_unit_id]" value="{{$product->unit->id}}">
            @if(!empty($sub_units))
                @php
                    // Pre-select the unit of the line being pulled in
                    // (purchase order / requisition lines are converted
                    // into their sub-unit's terms by the controller, so
                    // the pre-selected unit and the rendered quantity/
                    // prices are consistent). Falls back to the
                    // per-product default purchase sub-unit (e.g. Baby
                    // Box when the supplier always delivers in baby
                    // boxes) and then to the first unit in the list.
                    // Imported CSV rows keep the first (base) unit —
                    // their quantities/costs are raw from the file.
                    $_line_obj = null;
                    if (!empty($purchase_order_line)) {
                        $_line_obj = $purchase_order_line;
                    } elseif (!empty($purchase_requisition_line)) {
                        $_line_obj = $purchase_requisition_line;
                    } elseif (!empty($purchase_line)) {
                        $_line_obj = $purchase_line;
                    }

                    $_preselect_purchase = null;
                    if (!empty($_line_obj) && !empty($_line_obj->sub_unit_id) && isset($sub_units[$_line_obj->sub_unit_id])) {
                        $_preselect_purchase = $_line_obj->sub_unit_id;
                    } elseif (empty($imported_data) && !empty($product->default_purchase_sub_unit_id) && isset($sub_units[$product->default_purchase_sub_unit_id])) {
                        $_preselect_purchase = $product->default_purchase_sub_unit_id;
                    } else {
                        $_first = array_key_first($sub_units);
                        $_preselect_purchase = ($_first !== null) ? $_first : null;
                    }

                    // Rows whose prices were prefilled from a negotiated
                    // source (PO lines, imported CSV costs) must not have
                    // them recomputed from the product's default price
                    // when the pre-selected unit is applied on insert.
                    $_skip_price_sync = (!empty($purchase_order_line) || !empty($imported_data)) ? 1 : 0;
                @endphp
                <br>
                <select name="purchases[{{$row_count}}][sub_unit_id]" class="form-control input-sm sub_unit" @if($_skip_price_sync) data-skip_price_sync="1" @endif>
                    @foreach($sub_units as $key => $value)
                        <option value="{{$key}}" data-multiplier="{{$value['multiplier']}}" data-allow_decimal="{{$value['allow_decimal']}}" @if($_preselect_purchase == $key) selected @endif>
                            {{$value['name']}}
                        </option>
                    @endforeach
                </select>
            @else
                {{ $product->unit->short_name }}
            @endif

            @if(!empty($product->second_unit))
                @php
                    $secondary_unit_quantity = !empty($purchase_requisition_line) ? $purchase_requisition_line->secondary_unit_quantity : "";
                @endphp
                <br>
                <span style="white-space: nowrap;">
                @lang('lang_v1.quantity_in_second_unit', ['unit' => $product->second_unit->short_name])*:</span><br>
                <input type="text" 
                name="purchases[{{$row_count}}][secondary_unit_quantity]" 
                @if($secondary_unit_quantity !== '')value="{{@format_quantity($secondary_unit_quantity)}}" @endif
                class="form-control input-sm input_number"
                required>
            @endif
        </td>
        <td class="add_without_price_hide">
            @php
                $pp_without_discount = !empty($purchase_order_line) ? $purchase_order_line->pp_without_discount/$purchase_order->exchange_rate : $variation->default_purchase_price;

                $discount_percent = !empty($purchase_order_line) ? $purchase_order_line->discount_percent : 0;

                $purchase_price = !empty($purchase_order_line) ? $purchase_order_line->purchase_price/$purchase_order->exchange_rate : $variation->default_purchase_price;

                $tax_id = !empty($purchase_order_line) ? $purchase_order_line->tax_id : $product->tax;

                $tax_id = !empty($imported_data['tax_id']) ? $imported_data['tax_id'] : $tax_id;

                $pp_without_discount = !empty($imported_data['unit_cost_before_discount']) ? $imported_data['unit_cost_before_discount'] : $pp_without_discount;

                $discount_percent = !empty($imported_data['discount_percent']) ? $imported_data['discount_percent'] : $discount_percent;
            @endphp
            {!! Form::text('purchases[' . $row_count . '][pp_without_discount]',
            number_format($pp_without_discount, $currency_precision, $currency_details->decimal_separator, $currency_details->thousand_separator), ['class' => 'form-control input-sm purchase_unit_cost_without_discount input_number', 'required']); !!}

            @if(!empty($last_purchase_line))
                @php
                    // The stored last-purchase price is per BASE unit;
                    // scale it to the pre-selected sub-unit so the hint
                    // is comparable with the cost input beside it.
                    $_prev_price_multiplier = 1;
                    if (!empty($sub_units) && isset($_preselect_purchase) && isset($sub_units[$_preselect_purchase])) {
                        $_prev_price_multiplier = $sub_units[$_preselect_purchase]['multiplier'] ?? 1;
                    }
                @endphp
                <br>
                <small class="text-muted">@lang('lang_v1.prev_unit_price'): <span class="prev_unit_price_display" data-base_price="{{$last_purchase_line->pp_without_discount}}">@format_currency($last_purchase_line->pp_without_discount * $_prev_price_multiplier)</span></small>
            @endif
        </td>
        <td class="add_without_price_hide">
            {!! Form::text('purchases[' . $row_count . '][discount_percent]', number_format($discount_percent, $currency_precision, $currency_details->decimal_separator, $currency_details->thousand_separator), ['class' => 'form-control input-sm inline_discounts input_number', 'required']); !!}

            @if(!empty($last_purchase_line))
                <br>
                <small class="text-muted">
                    @lang('lang_v1.prev_discount'): 
                    {{@num_format($last_purchase_line->discount_percent)}}%
                </small>
            @endif
        </td>
        <td class="add_without_price_hide">
            {!! Form::text('purchases[' . $row_count . '][purchase_price]',
            number_format($purchase_price, $currency_precision, $currency_details->decimal_separator, $currency_details->thousand_separator), ['class' => 'form-control input-sm purchase_unit_cost input_number', 'required']); !!}
        </td>
        <td class="{{$hide_tax}} add_without_price_hide">
            <span class="row_subtotal_before_tax display_currency">0</span>
            <input type="hidden" class="row_subtotal_before_tax_hidden" value=0>
        </td>
        <td class="{{$hide_tax}} add_without_price_hide">
            <div class="input-group">
                <select name="purchases[{{ $row_count }}][purchase_line_tax_id]" class="form-control select2 input-sm purchase_line_tax_id" placeholder="'Please Select'">
                    <option value="" data-tax_amount="0" @if( $hide_tax == 'hide' )
                    selected @endif >@lang('lang_v1.none')</option>
                    @foreach($taxes as $tax)
                        <option value="{{ $tax->id }}" data-tax_amount="{{ $tax->amount }}" @if( $tax_id == $tax->id && $hide_tax != 'hide') selected @endif >{{ $tax->name }}</option>
                    @endforeach
                </select>
                {!! Form::hidden('purchases[' . $row_count . '][item_tax]', 0, ['class' => 'purchase_product_unit_tax']); !!}
                <span class="input-group-addon purchase_product_unit_tax_text">
                    0.00</span>
            </div>
        </td>
        <td class="{{$hide_tax}} add_without_price_hide">
            @php
                $dpp_inc_tax = number_format($variation->dpp_inc_tax, $currency_precision, $currency_details->decimal_separator, $currency_details->thousand_separator);
                if($hide_tax == 'hide'){
                    $dpp_inc_tax = number_format($variation->default_purchase_price, $currency_precision, $currency_details->decimal_separator, $currency_details->thousand_separator);
                }

                $dpp_inc_tax = !empty($purchase_order_line) ? number_format($purchase_order_line->purchase_price_inc_tax/$purchase_order->exchange_rate, $currency_precision, $currency_details->decimal_separator, $currency_details->thousand_separator) : $dpp_inc_tax;

            @endphp
            {!! Form::text('purchases[' . $row_count . '][purchase_price_inc_tax]', $dpp_inc_tax, ['class' => 'form-control input-sm purchase_unit_cost_after_tax input_number', 'required']); !!}
        </td>
        <td class="add_without_price_hide">
            <span class="row_subtotal_after_tax display_currency">0</span>
            <input type="hidden" class="row_subtotal_after_tax_hidden" value=0>
        </td>
        <td class="add_without_price_hide @if(!session('business.enable_editing_product_from_purchase') || !empty($is_purchase_order)) hide @endif">
            {!! Form::text('purchases[' . $row_count . '][profit_percent]', number_format($variation->profit_percent, $currency_precision, $currency_details->decimal_separator, $currency_details->thousand_separator), ['class' => 'form-control input-sm input_number profit_percent', 'required']); !!}
        </td>
        @if(empty($is_purchase_order))
        <td class="add_without_price_hide">
            @if(session('business.enable_editing_product_from_purchase'))
                {!! Form::text('purchases[' . $row_count . '][default_sell_price]', number_format($variation->sell_price_inc_tax, $currency_precision, $currency_details->decimal_separator, $currency_details->thousand_separator), ['class' => 'form-control input-sm input_number default_sell_price', 'required']); !!}
            @else
                {{ number_format($variation->sell_price_inc_tax, $currency_precision, $currency_details->decimal_separator, $currency_details->thousand_separator)}}
            @endif
        </td>
        @if(session('business.enable_lot_number'))
            @php
                $lot_number = !empty($imported_data['lot_number']) ? $imported_data['lot_number'] : null;
            @endphp
            <td>
                {!! Form::text('purchases[' . $row_count . '][lot_number]', $lot_number, ['class' => 'form-control input-sm']); !!}
            </td>
        @endif
        @if(session('business.enable_product_expiry'))
            <td style="text-align: left;">

                {{-- Maybe this condition for checkin expiry date need to be removed --}}
                @php
                    $expiry_period_type = !empty($product->expiry_period_type) ? $product->expiry_period_type : 'month';
                @endphp
                @if(!empty($expiry_period_type))
                <input type="hidden" class="row_product_expiry" value="{{ $product->expiry_period }}">
                <input type="hidden" class="row_product_expiry_type" value="{{ $expiry_period_type }}">

                @if(session('business.expiry_type') == 'add_manufacturing')
                    @php
                        $hide_mfg = false;
                    @endphp
                @else
                    @php
                        $hide_mfg = true;
                    @endphp
                @endif

                @php
                    $mfg_date = !empty($imported_data['mfg_date']) ? $imported_data['mfg_date'] : null;
                    $exp_date = !empty($imported_data['exp_date']) ? $imported_data['exp_date'] : null;
                @endphp

                <b class="@if($hide_mfg) hide @endif"><small>@lang('product.mfg_date'):</small></b>
                <div class="input-group @if($hide_mfg) hide @endif">
                    <span class="input-group-addon">
                        <i class="fa fa-calendar"></i>
                    </span>
                    {!! Form::text('purchases[' . $row_count . '][mfg_date]', $mfg_date, ['class' => 'form-control input-sm expiry_datepicker mfg_date', 'readonly']); !!}
                </div>
                <b><small>@lang('product.exp_date'):</small></b>
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fa fa-calendar"></i>
                    </span>
                    {!! Form::text('purchases[' . $row_count . '][exp_date]', $exp_date, ['class' => 'form-control input-sm expiry_datepicker exp_date', 'readonly']); !!}
                </div>
                @else
                <div class="text-center">
                    @lang('product.not_applicable')
                </div>
                @endif
            </td>
        @endif
        @endif
        @if(session('business.enable_damage_loss_tracking'))
            <td class="text-center">
                <button type="button" class="btn btn-xs btn-default damage_loss_btn" data-row="{{ $row_count }}" title="@lang('purchase.mark_damage_loss')">
                    <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                </button>
                <br>
                <small class="damage_loss_badge text-danger" style="white-space:nowrap;"></small>
                {!! Form::hidden('purchases[' . $row_count . '][quantity_damaged]', 0, ['class' => 'damage_loss_qty_damaged']); !!}
                {!! Form::hidden('purchases[' . $row_count . '][quantity_lost]', 0, ['class' => 'damage_loss_qty_lost']); !!}
                {!! Form::hidden('purchases[' . $row_count . '][damage_loss_reason]', '', ['class' => 'damage_loss_reason_hidden']); !!}
                {!! Form::hidden('purchases[' . $row_count . '][damage_loss_note]', '', ['class' => 'damage_loss_note_hidden']); !!}
            </td>
        @endif
        <?php $row_count++ ;?>

        <td><i class="fa fa-times remove_purchase_entry_row text-danger" title="Remove" style="cursor:pointer;"></i></td>
    </tr>
@endforeach

<input type="hidden" id="row_count" value="{{ $row_count }}">