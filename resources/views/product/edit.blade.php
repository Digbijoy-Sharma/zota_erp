@extends('layouts.app')
@section('title', __('product.edit_product'))

@section('content')

@php
  $is_image_required = !empty($common_settings['is_product_image_required']) && empty($product->image);
@endphp

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('product.edit_product')</h1>
    <!-- <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Level</a></li>
        <li class="active">Here</li>
    </ol> -->
</section>

<!-- Main content -->
<section class="content">
{!! Form::open(['url' => action([\App\Http\Controllers\ProductController::class, 'update'] , [$product->id] ), 'method' => 'PUT', 'id' => 'product_add_form',
        'class' => 'product_form', 'files' => true ]) !!}
    <input type="hidden" id="product_id" value="{{ $product->id }}">

    @component('components.widget', ['class' => 'box-primary'])
        <div class="row">
            <div class="col-sm-4">
              <div class="form-group">
                {!! Form::label('name', __('product.product_name') . ':*') !!}
                  {!! Form::text('name', $product->name, ['class' => 'form-control', 'required',
                  'placeholder' => __('product.product_name')]); !!}
              </div>
            </div>

            <div class="col-sm-4">
              <div class="form-group">
                {!! Form::label('sku', __('product.sku')  . ':*') !!} @show_tooltip(__('tooltip.sku'))
                {!! Form::text('sku', $product->sku, ['class' => 'form-control',
                'placeholder' => __('product.sku'), 'required']); !!}
              </div>
            </div>

            <div class="col-sm-4">
              <div class="form-group">
                {!! Form::label('barcode_type', __('product.barcode_type') . ':*') !!}
                  {!! Form::select('barcode_type', $barcode_types, $product->barcode_type, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2', 'required']); !!}
              </div>
            </div>

            <div class="clearfix"></div>
            
            <div class="col-sm-4">
              <div class="form-group">
                {!! Form::label('unit_id', __('product.unit') . ':*') !!}
                @show_tooltip(__('product.base_unit_tooltip'))
                <div class="input-group">
                  {!! Form::select('unit_id', $units, $product->unit_id, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2', 'required']); !!}
                  <span class="input-group-btn">
                    <button type="button" @if(!auth()->user()->can('unit.create')) disabled @endif class="btn btn-default bg-white btn-flat quick_add_unit btn-modal" data-href="{{action([\App\Http\Controllers\UnitController::class, 'create'], ['quick_add' => true])}}" title="@lang('unit.add_unit')" data-container=".view_modal"><i class="fa fa-plus-circle text-primary fa-lg"></i></button>
                  </span>
                </div>
              </div>
            </div>

            <div class="col-sm-4 @if(!session('business.enable_sub_units')) hide @endif">
              <div class="form-group">
                {!! Form::label('sub_unit_ids', __('lang_v1.related_sub_units') . ':') !!} @show_tooltip(__('lang_v1.sub_units_tooltip'))

                <select name="sub_unit_ids[]" class="form-control select2" multiple id="sub_unit_ids">
                  @foreach($sub_units as $sub_unit_id => $sub_unit_value)
                    <option value="{{$sub_unit_id}}"
                      @if(is_array($product->sub_unit_ids) &&in_array($sub_unit_id, $product->sub_unit_ids))   selected
                      @endif>{{$sub_unit_value['name']}}</option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="clearfix"></div>
            <div class="col-sm-3 @if(!session('business.enable_sub_units')) hide @endif default-sub-unit-fields">
              <div class="form-group">
                {!! Form::label('default_sell_sub_unit_id', __('lang_v1.default_sell_sub_unit') . ':') !!}
                @show_tooltip(__('lang_v1.default_sell_sub_unit_help'))
                @php
                  // Hand-built <select>: Form::select with [] drops the
                  // selected value, so we render the options ourselves
                  // and pre-include the saved value (even if it is no
                  // longer in the current sub_unit_ids list).
                  $saved_sell_id = $product->default_sell_sub_unit_id;
                  $saved_purchase_id = $product->default_purchase_sub_unit_id;
                  $saved_sell_unit = ! empty($saved_sell_id) ? \App\Unit::find($saved_sell_id) : null;
                  $saved_purchase_unit = ! empty($saved_purchase_id) ? \App\Unit::find($saved_purchase_id) : null;
                @endphp
                <select name="default_sell_sub_unit_id" class="form-control select2" id="default_sell_sub_unit_id" data-initial="{{ $saved_sell_id }}">
                  <option value="">@lang('messages.please_select')</option>
                  @foreach($sub_units as $id => $sub_unit_value)
                    <option value="{{ $id }}" @if($id == $saved_sell_id) selected @endif>{{ $sub_unit_value['name'] }}</option>
                  @endforeach
                  @if($saved_sell_unit && !isset($sub_units[$saved_sell_id]))
                    <option value="{{ $saved_sell_id }}" selected>{{ $saved_sell_unit->actual_name }}</option>
                  @endif
                </select>
              </div>
            </div>
            <div class="col-sm-3 @if(!session('business.enable_sub_units')) hide @endif default-sub-unit-fields">
              <div class="form-group">
                {!! Form::label('default_purchase_sub_unit_id', __('lang_v1.default_purchase_sub_unit') . ':') !!}
                @show_tooltip(__('lang_v1.default_purchase_sub_unit_help'))
                <select name="default_purchase_sub_unit_id" class="form-control select2" id="default_purchase_sub_unit_id" data-initial="{{ $saved_purchase_id }}">
                  <option value="">@lang('messages.please_select')</option>
                  @foreach($sub_units as $id => $sub_unit_value)
                    <option value="{{ $id }}" @if($id == $saved_purchase_id) selected @endif>{{ $sub_unit_value['name'] }}</option>
                  @endforeach
                  @if($saved_purchase_unit && !isset($sub_units[$saved_purchase_id]))
                    <option value="{{ $saved_purchase_id }}" selected>{{ $saved_purchase_unit->actual_name }}</option>
                  @endif
                </select>
              </div>
            </div>
            <div class="col-sm-3 @if(!session('business.enable_sub_units')) hide @endif default-sub-unit-fields">
              <div class="form-group">
                {!! Form::label('sell_sub_unit_ids', __('lang_v1.sell_sub_units') . ':') !!}
                @show_tooltip(__('lang_v1.sell_sub_units_help'))
                {!! Form::select('sell_sub_unit_ids[]', [], null, ['class' => 'form-control select2', 'multiple', 'id' => 'sell_sub_unit_ids', 'data-initial' => !empty($product->sell_sub_unit_ids) ? json_encode($product->sell_sub_unit_ids) : null]); !!}
              </div>
            </div>
            <div class="col-sm-3 @if(!session('business.enable_sub_units')) hide @endif default-sub-unit-fields">
              <div class="form-group">
                {!! Form::label('purchase_sub_unit_ids', __('lang_v1.purchase_sub_units') . ':') !!}
                @show_tooltip(__('lang_v1.purchase_sub_units_help'))
                {!! Form::select('purchase_sub_unit_ids[]', [], null, ['class' => 'form-control select2', 'multiple', 'id' => 'purchase_sub_unit_ids', 'data-initial' => !empty($product->purchase_sub_unit_ids) ? json_encode($product->purchase_sub_unit_ids) : null]); !!}
              </div>
            </div>

            @if(!empty($common_settings['enable_secondary_unit']))
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('secondary_unit_id', __('lang_v1.secondary_unit') . ':') !!} @show_tooltip(__('lang_v1.secondary_unit_help'))
                        {!! Form::select('secondary_unit_id', $units, $product->secondary_unit_id, ['class' => 'form-control select2']); !!}
                    </div>
                </div>
            @endif

            <div class="col-sm-4 @if(!session('business.enable_brand')) hide @endif">
              <div class="form-group">
                {!! Form::label('brand_id', __('product.brand') . ':') !!}
                <div class="input-group">
                  {!! Form::select('brand_id', $brands, $product->brand_id, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2']); !!}
                  <span class="input-group-btn">
                    <button type="button" @if(!auth()->user()->can('brand.create')) disabled @endif class="btn btn-default bg-white btn-flat btn-modal" data-href="{{action([\App\Http\Controllers\BrandController::class, 'create'], ['quick_add' => true])}}" title="@lang('brand.add_brand')" data-container=".view_modal"><i class="fa fa-plus-circle text-primary fa-lg"></i></button>
                  </span>
                </div>
              </div>
            </div>
            <div class="col-sm-4 @if(!session('business.enable_category')) hide @endif">
              <div class="form-group">
                {!! Form::label('category_id', __('product.category') . ':') !!}
                  {!! Form::select('category_id', $categories, $product->category_id, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2']); !!}
              </div>
            </div>

            <div class="col-sm-4 @if(!(session('business.enable_category') && session('business.enable_sub_category'))) hide @endif">
              <div class="form-group">
                {!! Form::label('sub_category_id', __('product.sub_category')  . ':') !!}
                  {!! Form::select('sub_category_id', $sub_categories, $product->sub_category_id, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2']); !!}
              </div>
            </div>

            <div class="col-sm-4">
              <div class="form-group">
                {!! Form::label('composition_id', __('composition.composition') . ':') !!}
                {!! Form::select('composition_id', $compositions, $product->composition_id, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2']); !!}
                <p class="help-block">@lang('composition.product_form_help')</p>
              </div>
            </div>

            <div class="col-sm-4">
              <div class="form-group">
                {!! Form::label('hsn_code', __('lang_v1.hsn_code') . ':') !!}
                {!! Form::text('hsn_code', $product->hsn_code, ['class' => 'form-control', 'placeholder' => __('lang_v1.hsn_code')]); !!}
              </div>
            </div>

            <div class="col-sm-4">
              <div class="form-group">
                {!! Form::label('drug_schedule', __('lang_v1.drug_schedule') . ':') !!}
                {!! Form::select('drug_schedule', $drug_schedules, $product->drug_schedule, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2']); !!}
              </div>
            </div>

            <div class="col-sm-4">
              <div class="form-group">
                {!! Form::label('dosage_form', __('lang_v1.dosage_form') . ':') !!}
                {!! Form::select('dosage_form', $dosage_forms, $product->dosage_form, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2']); !!}
              </div>
            </div>

            <div class="col-sm-4">
              <div class="form-group">
                {!! Form::label('storage_condition', __('lang_v1.storage_condition') . ':') !!}
                {!! Form::select('storage_condition', $storage_conditions, $product->storage_condition, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2']); !!}
              </div>
            </div>

            <div class="clearfix"></div>

            <div class="col-sm-4">
              <div class="form-group">
                {!! Form::label('manufacturer_id', __('lang_v1.manufacturer') . ':') !!}
                <div class="input-group">
                    {!! Form::select('manufacturer_id', $manufacturers, $product->manufacturer_id, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2']); !!}
                    <span class="input-group-btn">
                        <button type="button" @if(!auth()->user()->can('manufacturer.create')) disabled @endif class="btn btn-default bg-white btn-flat btn-modal" data-href="{{action([\App\Http\Controllers\ManufacturerController::class, 'create'], ['quick_add' => true])}}" title="@lang('lang_v1.add_manufacturer')" data-container=".view_modal"><i class="fa fa-plus-circle text-primary fa-lg"></i></button>
                    </span>
                </div>
              </div>
            </div>

            <div class="col-sm-4">
              <div class="form-group">
                {!! Form::label('division_id', __('lang_v1.division') . ':') !!}
                <div class="input-group">
                    {!! Form::select('division_id', $divisions, $product->division_id, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2']); !!}
                    <span class="input-group-btn">
                        <button type="button" @if(!auth()->user()->can('division.create')) disabled @endif class="btn btn-default bg-white btn-flat btn-modal" data-href="{{action([\App\Http\Controllers\DivisionController::class, 'create'], ['quick_add' => true])}}" title="@lang('lang_v1.add_division')" data-container=".view_modal"><i class="fa fa-plus-circle text-primary fa-lg"></i></button>
                    </span>
                </div>
              </div>
            </div>

            {{-- 'Tags' field removed — movement tags are per-store & auto-computed. --}}

            <div class="col-sm-4">
              <div class="form-group">
                {!! Form::label('product_locations', __('business.business_locations') . ':') !!} @show_tooltip(__('lang_v1.product_location_help'))
                  {!! Form::select('product_locations[]', $business_locations, $product->product_locations->pluck('id'), ['class' => 'form-control select2', 'multiple', 'id' => 'product_locations']); !!}
              </div>
            </div>

            <div class="clearfix"></div>

            <div class="col-sm-4">
              <div class="form-group">
              <br>
                <label>
                  {!! Form::checkbox('enable_stock', 1, $product->enable_stock, ['class' => 'input-icheck', 'id' => 'enable_stock']); !!} <strong>@lang('product.manage_stock')</strong>
                </label>@show_tooltip(__('tooltip.enable_stock')) <p class="help-block"><i>@lang('product.enable_stock_help')</i></p>
              </div>
            </div>

            {{-- 'Prescription required' removed — derived from Drug Schedule (H/H1/X) on save. --}}

            <div class="col-sm-4">
              <div class="form-group">
              <br>
                <label>
                  {!! Form::checkbox('can_be_purchased', 1, $product->can_be_purchased, ['class' => 'input-icheck']); !!} <strong>@lang('lang_v1.can_be_purchased')</strong>
                </label>
              </div>
            </div>

            <div class="clearfix"></div>

            <div class="col-sm-4">
              <div class="form-group">
              <br>
                <label>
                  {!! Form::checkbox('can_be_stored', 1, $product->can_be_stored, ['class' => 'input-icheck']); !!} <strong>@lang('lang_v1.can_be_stored')</strong>
                </label>
              </div>
            </div>

            <div class="col-sm-4">
              <div class="form-group">
              <br>
                <label>
                  {!! Form::checkbox('can_be_sold', 1, $product->can_be_sold, ['class' => 'input-icheck']); !!} <strong>@lang('lang_v1.can_be_sold')</strong>
                </label>
              </div>
            </div>

            {{-- Alert quantity is no longer entered separately — the
                 low-stock / reorder threshold comes from the per-store Min
                 stock (variation_location_details.min_quantity). Kept as a
                 hidden field to preserve the value and the toggle JS targets. --}}
            <div id="alert_quantity_div" style="display:none;">
                {!! Form::hidden('alert_quantity', $alert_quantity, ['id' => 'alert_quantity']); !!}
            </div>
            @if(!empty($common_settings['enable_product_warranty']))
            <div class="col-sm-4">
              <div class="form-group">
                {!! Form::label('warranty_id', __('lang_v1.warranty') . ':') !!}
                {!! Form::select('warranty_id', $warranties, $product->warranty_id, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select')]); !!}
              </div>
            </div>
            @endif
            <!-- include module fields -->
            @if(!empty($pos_module_data))
                @foreach($pos_module_data as $key => $value)
                    @if(!empty($value['view_path']))
                        @includeIf($value['view_path'], ['view_data' => $value['view_data']])
                    @endif
                @endforeach
            @endif
            <div class="clearfix"></div>
            <div class="col-sm-8">
              <div class="form-group">
                {!! Form::label('product_description', __('lang_v1.product_description') . ':') !!}
                  {!! Form::textarea('product_description', $product->product_description, ['class' => 'form-control']); !!}
              </div>
            </div>
            <div class="col-sm-4">
              <div class="form-group">
                {!! Form::label('image', __('lang_v1.product_image') . ':') !!}
                {!! Form::file('image', ['id' => 'upload_image', 'accept' => 'image/*', 'required' => $is_image_required]); !!}
                <small><p class="help-block">@lang('purchase.max_file_size', ['size' => (config('constants.document_size_limit') / 1000000)]). @lang('lang_v1.aspect_ratio_should_be_1_1') @if(!empty($product->image)) <br> @lang('lang_v1.previous_image_will_be_replaced') @endif</p></small>
              </div>
            </div>
            </div>
            <div class="col-sm-4">
              <div class="form-group">
                {!! Form::label('product_brochure', __('lang_v1.product_brochure') . ':') !!}
                {!! Form::file('product_brochure', ['id' => 'product_brochure', 'accept' => implode(',', array_keys(config('constants.document_upload_mimes_types')))]); !!}
                <small>
                    <p class="help-block">
                        @lang('lang_v1.previous_file_will_be_replaced')<br>
                        @lang('purchase.max_file_size', ['size' => (config('constants.document_size_limit') / 1000000)])
                        @includeIf('components.document_help_text')
                    </p>
                </small>
              </div>
            </div>
    @endcomponent

    @component('components.widget', ['class' => 'box-primary'])
        <div class="row">
        @if(session('business.enable_product_expiry'))

          @if(session('business.expiry_type') == 'add_expiry')
            @php
              $expiry_period = 12;
              $hide = true;
            @endphp
          @else
            @php
              $expiry_period = null;
              $hide = false;
            @endphp
          @endif
          <div class="col-sm-4 @if($hide) hide @endif">
            <div class="form-group">
              <div class="multi-input">
                @php
                  $disabled = false;
                  $disabled_period = false;
                  if( empty($product->expiry_period_type) || empty($product->enable_stock) ){
                    $disabled = true;
                  }
                  if( empty($product->enable_stock) ){
                    $disabled_period = true;
                  }
                @endphp
                  {!! Form::label('expiry_period', __('product.expires_in') . ':') !!}<br>
                  {!! Form::text('expiry_period', @num_format($product->expiry_period), ['class' => 'form-control pull-left input_number',
                    'placeholder' => __('product.expiry_period'), 'style' => 'width:60%;', 'disabled' => $disabled]); !!}
                  {!! Form::select('expiry_period_type', ['months'=>__('product.months'), 'days'=>__('product.days'), '' =>__('product.not_applicable') ], $product->expiry_period_type, ['class' => 'form-control select2 pull-left', 'style' => 'width:40%;', 'id' => 'expiry_period_type', 'disabled' => $disabled_period]); !!}
              </div>
            </div>
          </div>
          @endif
          <div class="col-sm-4">
            <div class="checkbox">
              <label>
                {!! Form::checkbox('enable_sr_no', 1, $product->enable_sr_no, ['class' => 'input-icheck']); !!} <strong>@lang('lang_v1.enable_imei_or_sr_no')</strong>
              </label>
              @show_tooltip(__('lang_v1.tooltip_sr_no'))
            </div>
          </div>

          {{-- 'Not for selling' removed — inverse of 'Can be sold'; derived on save. --}}

        <div class="clearfix"></div>

        <!-- Rack, Row & position number -->
        @if(session('business.enable_racks') || session('business.enable_row') || session('business.enable_position'))
          <div class="col-md-12">
            <h4>@lang('lang_v1.rack_details'):
              @show_tooltip(__('lang_v1.tooltip_rack_details'))
            </h4>
          </div>
          @foreach($business_locations as $id => $location)
            <div class="col-sm-3">
              <div class="form-group">
                {!! Form::label('rack_' . $id,  $location . ':') !!}

                
                  @if(!empty($rack_details[$id]))
                    @if(session('business.enable_racks'))
                      {!! Form::text('product_racks_update[' . $id . '][rack]', $rack_details[$id]['rack'], ['class' => 'form-control', 'id' => 'rack_' . $id]); !!}
                    @endif

                    @if(session('business.enable_row'))
                      {!! Form::text('product_racks_update[' . $id . '][row]', $rack_details[$id]['row'], ['class' => 'form-control']); !!}
                    @endif

                    @if(session('business.enable_position'))
                      {!! Form::text('product_racks_update[' . $id . '][position]', $rack_details[$id]['position'], ['class' => 'form-control']); !!}
                    @endif
                  @else
                    {!! Form::text('product_racks[' . $id . '][rack]', null, ['class' => 'form-control', 'id' => 'rack_' . $id, 'placeholder' => __('lang_v1.rack')]); !!}

                    {!! Form::text('product_racks[' . $id . '][row]', null, ['class' => 'form-control', 'placeholder' => __('lang_v1.row')]); !!}

                    {!! Form::text('product_racks[' . $id . '][position]', null, ['class' => 'form-control', 'placeholder' => __('lang_v1.position')]); !!}
                  @endif

              </div>
            </div>
          @endforeach
        @endif


        <div class="clearfix"></div>
        
        @php
            $custom_labels = json_decode(session('business.custom_labels'), true);
            $product_custom_fields = !empty($custom_labels['product']) ? $custom_labels['product'] : [];
            $product_cf_details = !empty($custom_labels['product_cf_details']) ? $custom_labels['product_cf_details'] : [];
        @endphp
        <!--custom fields-->

        @foreach($product_custom_fields as $index => $cf)
            @if(!empty($cf))
                @php
                    $db_field_name = 'product_custom_field' . $loop->iteration;
                    $cf_type = !empty($product_cf_details[$loop->iteration]['type']) ? $product_cf_details[$loop->iteration]['type'] : 'text';
                    $dropdown = !empty($product_cf_details[$loop->iteration]['dropdown_options']) ? explode(PHP_EOL, $product_cf_details[$loop->iteration]['dropdown_options']) : [];
                @endphp

                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label($db_field_name, $cf . ':') !!}
                        @if(in_array($cf_type, ['text', 'date']))
                            <input type="{{$cf_type}}" name="{{$db_field_name}}" id="{{$db_field_name}}" 
                            value="{{$product->$db_field_name}}" class="form-control" placeholder="{{$cf}}">
                        @elseif($cf_type == 'dropdown')
                            <!-- {!! Form::select($db_field_name, $dropdown, $product->$db_field_name, ['placeholder' => $cf, 'class' => 'form-control select2']); !!} -->
                             <select name="{{$db_field_name}}" id="{{$db_field_name}}" class="form-control select2">
                                @foreach($dropdown as $option)
                                    <option value="{{$option}}" @if($option == $product->$db_field_name) selected @endif>{{$option}}</option>
                                @endforeach
                             </select>
                        @endif
                    </div>
                </div>
            @endif
        @endforeach

        <!--custom fields-->
        @include('layouts.partials.module_form_part')
        </div>
    @endcomponent

    @component('components.widget', ['class' => 'box-primary'])
        <div class="row">
            <div class="col-sm-4 @if(!session('business.enable_price_tax')) hide @endif">
              <div class="form-group">
                {!! Form::label('tax', __('product.applicable_tax') . ':') !!}
                  {!! Form::select('tax', $taxes, $product->tax, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2'], $tax_attributes); !!}
              </div>
            </div>

            <div class="col-sm-4 @if(!session('business.enable_price_tax')) hide @endif">
              <div class="form-group">
                {!! Form::label('tax_type', __('product.selling_price_tax_type') . ':*') !!}
                  {!! Form::select('tax_type',['inclusive' => __('product.inclusive'), 'exclusive' => __('product.exclusive')], $product->tax_type,
                  ['class' => 'form-control select2', 'required']); !!}
              </div>
            </div>

            <div class="clearfix"></div>
            <div class="col-sm-4">
              <div class="form-group">
                {!! Form::label('type', __('product.product_type') . ':*') !!} @show_tooltip(__('tooltip.product_type'))
                {!! Form::select('type', $product_types, $product->type, ['class' => 'form-control select2',
                  'required','disabled', 'data-action' => 'edit', 'data-product_id' => $product->id ]); !!}
              </div>
            </div>

            <div class="form-group col-sm-12" id="product_form_part"></div>
            <input type="hidden" id="variation_counter" value="0">
            <input type="hidden" id="default_profit_percent" value="{{ $default_profit_percent }}">
            </div>
    @endcomponent

  <div class="row">
    <input type="hidden" name="submit_type" id="submit_type">
        <div class="col-sm-12">
          <div class="text-center">
            <div class="btn-group">
              @if($selling_price_group_count)
                <button type="submit" value="submit_n_add_selling_prices" class="tw-dw-btn tw-dw-btn-warning tw-text-white tw-dw-btn-lg submit_product_form">@lang('lang_v1.save_n_add_selling_price_group_prices')</button>
              @endif

              @can('product.opening_stock')
              <button type="submit" @if(empty($product->enable_stock)) disabled="true" @endif id="opening_stock_button"  value="update_n_edit_opening_stock" class="tw-dw-btn tw-text-white tw-dw-btn-lg bg-purple submit_product_form">@lang('lang_v1.update_n_edit_opening_stock')</button>
              @endif

              <button type="submit" value="save_n_add_another" class="tw-dw-btn tw-text-white tw-dw-btn-lg bg-maroon submit_product_form">@lang('lang_v1.update_n_add_another')</button>

              <button type="submit" value="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white tw-dw-btn-lg submit_product_form">@lang('messages.update')</button>
            </div>
          </div>
        </div>
  </div>
{!! Form::close() !!}
</section>
<!-- /.content -->

@endsection

@section('javascript')
  <script src="{{ asset('js/product.js?v=' . $asset_v) }}"></script>
  <script type="text/javascript">
    $(document).ready( function(){
      __page_leave_confirmation('#product_add_form');

      // Mirror the create form's behaviour: keep the default
      // sell / purchase sub-unit selects populated from the
      // Related Sub Units multi-select + the product's own
      // base unit.
      function refreshDefaultSubUnitOptions() {
        var selected = ($('#sub_unit_ids').val() || []).slice();
        var baseId = $('select[name="unit_id"]').val();
        if (baseId && selected.indexOf(baseId) === -1) {
          selected.unshift(baseId);
        }

        var $sell = $('#default_sell_sub_unit_id');
        var $purchase = $('#default_purchase_sub_unit_id');
        // Prefer what the user currently has selected (read before
        // the options are rebuilt); fall back to the saved value
        // injected by the server via data-initial on first load,
        // when the select has no options yet and val() is empty.
        var sellVal = $sell.val() || $sell.data('initial');
        var purchaseVal = $purchase.val() || $purchase.data('initial');

        $sell.empty().append('<option value="">@lang("messages.please_select")</option>');
        $purchase.empty().append('<option value="">@lang("messages.please_select")</option>');

        var optsById = {};
        $('#sub_unit_ids option').each(function () {
          optsById[this.value] = $(this).text();
        });
        $('select[name="unit_id"] option').each(function () {
          if (this.value) {
            optsById[this.value] = $(this).text();
          }
        });

        selected.forEach(function (id) {
          var label = optsById[id] || id;
          $sell.append('<option value="' + id + '">' + label + '</option>');
          $purchase.append('<option value="' + id + '">' + label + '</option>');
        });

        if (sellVal && selected.indexOf(sellVal.toString()) !== -1) {
          $sell.val(sellVal);
          // The saved value has been applied; drop the marker so
          // later refreshes respect the user's own choice
          // (including deliberately clearing the default).
          $sell.removeData('initial').removeAttr('data-initial');
        } else {
          $sell.val('');
        }
        if (purchaseVal && selected.indexOf(purchaseVal.toString()) !== -1) {
          $purchase.val(purchaseVal);
          $purchase.removeData('initial').removeAttr('data-initial');
        } else {
          $purchase.val('');
        }

        // Rebuild the sellable/purchasable unit whitelists from the
        // same allowed-unit list, preserving current selections (or
        // the saved values injected via data-initial on first load).
        ['#sell_sub_unit_ids', '#purchase_sub_unit_ids'].forEach(function (sel) {
          var $el = $(sel);
          if (!$el.length) {
            return;
          }
          var current = ($el.val() || []).map(String);
          if (!current.length && $el.data('initial')) {
            var initial = $el.data('initial');
            if (typeof initial === 'string') {
              try { initial = JSON.parse(initial); } catch (e) { initial = []; }
            }
            current = (initial || []).map(String);
          }
          $el.empty();
          selected.forEach(function (id) {
            var label = optsById[id] || id;
            var selected_attr = current.indexOf(id.toString()) !== -1 ? ' selected' : '';
            $el.append('<option value="' + id + '"' + selected_attr + '>' + label + '</option>');
          });
          if (current.length) {
            $el.removeData('initial').removeAttr('data-initial');
          }
          $el.trigger('change.select2');
        });

        // Keep the select2 widgets' rendered text in sync with the
        // underlying selects regardless of initialisation order.
        // Namespaced so the form's dirty-tracking (page-leave
        // confirmation) is not triggered by this programmatic sync.
        $sell.trigger('change.select2');
        $purchase.trigger('change.select2');
      }

      $(document).on('change', '#sub_unit_ids, select[name="unit_id"]', function () {
        setTimeout(refreshDefaultSubUnitOptions, 30);
      });

      // Run once the page is ready (sub_unit_ids is pre-rendered on
      // edit); this populates the options and re-applies the saved
      // defaults injected via data-initial.
      refreshDefaultSubUnitOptions();
    });
  </script>
@endsection