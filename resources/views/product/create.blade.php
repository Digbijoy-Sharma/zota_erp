@extends('layouts.app')
@section('title', __('product.add_new_product'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('product.add_new_product')</h1>
    <!-- <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Level</a></li>
        <li class="active">Here</li>
    </ol> -->
</section>

<!-- Main content -->
<section class="content">
    @php
    $form_class = empty($duplicate_product) ? 'create' : '';
    $is_image_required = !empty($common_settings['is_product_image_required']);
    @endphp
    {!! Form::open(['url' => action([\App\Http\Controllers\ProductController::class, 'store']), 'method' => 'post',
    'id' => 'product_add_form','class' => 'product_form ' . $form_class, 'files' => true ]) !!}
    @component('components.widget', ['class' => 'box-primary'])
    <div class="row">
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('name', __('product.product_name') . ':*') !!}
                {!! Form::text('name', !empty($duplicate_product->name) ? $duplicate_product->name : null, ['class' => 'form-control', 'required',
                'placeholder' => __('product.product_name')]); !!}
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('sku', __('product.sku') . ':') !!} @show_tooltip(__('tooltip.sku'))
                {!! Form::text('sku', null, ['class' => 'form-control',
                'placeholder' => __('product.sku')]); !!}
            </div>
        </div>
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('barcode_type', __('product.barcode_type') . ':*') !!}
                {!! Form::select('barcode_type', $barcode_types, !empty($duplicate_product->barcode_type) ? $duplicate_product->barcode_type : $barcode_default, ['class' => 'form-control select2', 'required']); !!}
            </div>
        </div>

        <div class="clearfix"></div>
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('unit_id', __('product.unit') . ':*') !!}
                @show_tooltip(__('product.base_unit_tooltip'))
                <div class="input-group">
                    {!! Form::select('unit_id', $units, !empty($duplicate_product->unit_id) ? $duplicate_product->unit_id : session('business.default_unit'), ['class' => 'form-control select2', 'required']); !!}
                    <span class="input-group-btn">
                        <button type="button" @if(!auth()->user()->can('unit.create')) disabled @endif class="btn btn-default bg-white btn-flat btn-modal" data-href="{{action([\App\Http\Controllers\UnitController::class, 'create'], ['quick_add' => true])}}" title="@lang('unit.add_unit')" data-container=".view_modal"><i class="fa fa-plus-circle text-primary fa-lg"></i></button>
                    </span>
                </div>
            </div>
        </div>

        <div class="col-sm-4 @if(!session('business.enable_sub_units')) hide @endif">
            <div class="form-group">
                {!! Form::label('sub_unit_ids', __('lang_v1.related_sub_units') . ':') !!} @show_tooltip(__('lang_v1.sub_units_tooltip'))

                {!! Form::select('sub_unit_ids[]', [], !empty($duplicate_product->sub_unit_ids) ? $duplicate_product->sub_unit_ids : null, ['class' => 'form-control select2', 'multiple', 'id' => 'sub_unit_ids']); !!}
            </div>
        </div>
        @if(!empty($common_settings['enable_secondary_unit']))
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('secondary_unit_id', __('lang_v1.secondary_unit') . ':') !!} @show_tooltip(__('lang_v1.secondary_unit_help'))
                {!! Form::select('secondary_unit_id', $units, !empty($duplicate_product->secondary_unit_id) ? $duplicate_product->secondary_unit_id : null, ['class' => 'form-control select2']); !!}
            </div>
        </div>
        @endif
        <div class="clearfix"></div>
        <div class="col-sm-3 @if(!session('business.enable_sub_units')) hide @endif default-sub-unit-fields">
            <div class="form-group">
                {!! Form::label('default_sell_sub_unit_id', __('lang_v1.default_sell_sub_unit') . ':') !!}
                @show_tooltip(__('lang_v1.default_sell_sub_unit_help'))
                {!! Form::select('default_sell_sub_unit_id', [], null, ['class' => 'form-control select2', 'id' => 'default_sell_sub_unit_id', 'placeholder' => __('messages.please_select'), 'data-initial' => !empty($duplicate_product->default_sell_sub_unit_id) ? $duplicate_product->default_sell_sub_unit_id : null]); !!}
            </div>
        </div>
        <div class="col-sm-3 @if(!session('business.enable_sub_units')) hide @endif default-sub-unit-fields">
            <div class="form-group">
                {!! Form::label('default_purchase_sub_unit_id', __('lang_v1.default_purchase_sub_unit') . ':') !!}
                @show_tooltip(__('lang_v1.default_purchase_sub_unit_help'))
                {!! Form::select('default_purchase_sub_unit_id', [], null, ['class' => 'form-control select2', 'id' => 'default_purchase_sub_unit_id', 'placeholder' => __('messages.please_select'), 'data-initial' => !empty($duplicate_product->default_purchase_sub_unit_id) ? $duplicate_product->default_purchase_sub_unit_id : null]); !!}
            </div>
        </div>
        <div class="col-sm-3 @if(!session('business.enable_sub_units')) hide @endif default-sub-unit-fields">
            <div class="form-group">
                {!! Form::label('sell_sub_unit_ids', __('lang_v1.sell_sub_units') . ':') !!}
                @show_tooltip(__('lang_v1.sell_sub_units_help'))
                {!! Form::select('sell_sub_unit_ids[]', [], null, ['class' => 'form-control select2', 'multiple', 'id' => 'sell_sub_unit_ids', 'data-initial' => !empty($duplicate_product->sell_sub_unit_ids) ? json_encode($duplicate_product->sell_sub_unit_ids) : null]); !!}
            </div>
        </div>
        <div class="col-sm-3 @if(!session('business.enable_sub_units')) hide @endif default-sub-unit-fields">
            <div class="form-group">
                {!! Form::label('purchase_sub_unit_ids', __('lang_v1.purchase_sub_units') . ':') !!}
                @show_tooltip(__('lang_v1.purchase_sub_units_help'))
                {!! Form::select('purchase_sub_unit_ids[]', [], null, ['class' => 'form-control select2', 'multiple', 'id' => 'purchase_sub_unit_ids', 'data-initial' => !empty($duplicate_product->purchase_sub_unit_ids) ? json_encode($duplicate_product->purchase_sub_unit_ids) : null]); !!}
            </div>
        </div>

        <div class="col-sm-4 @if(!session('business.enable_brand')) hide @endif">
            <div class="form-group">
                {!! Form::label('brand_id', __('product.brand') . ':') !!}
                <div class="input-group">
                    {!! Form::select('brand_id', $brands, !empty($duplicate_product->brand_id) ? $duplicate_product->brand_id : null, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2']); !!}
                    <span class="input-group-btn">
                        <button type="button" @if(!auth()->user()->can('brand.create')) disabled @endif class="btn btn-default bg-white btn-flat btn-modal" data-href="{{action([\App\Http\Controllers\BrandController::class, 'create'], ['quick_add' => true])}}" title="@lang('brand.add_brand')" data-container=".view_modal"><i class="fa fa-plus-circle text-primary fa-lg"></i></button>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-sm-4 @if(!session('business.enable_category')) hide @endif">
            <div class="form-group">
                {!! Form::label('category_id', __('product.category') . ':') !!}
                {!! Form::select('category_id', $categories, !empty($duplicate_product->category_id) ? $duplicate_product->category_id : null, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2']); !!}
            </div>
        </div>

        <div class="col-sm-4 @if(!(session('business.enable_category') && session('business.enable_sub_category'))) hide @endif">
            <div class="form-group">
                {!! Form::label('sub_category_id', __('product.sub_category') . ':') !!}
                {!! Form::select('sub_category_id', $sub_categories, !empty($duplicate_product->sub_category_id) ? $duplicate_product->sub_category_id : null, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2']); !!}
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('composition_id', __('composition.composition') . ':') !!}
                {!! Form::select('composition_id', $compositions, !empty($duplicate_product->composition_id) ? $duplicate_product->composition_id : null, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2']); !!}
                <p class="help-block">@lang('composition.product_form_help')</p>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('hsn_code', __('lang_v1.hsn_code') . ':') !!}
                {!! Form::text('hsn_code', !empty($duplicate_product->hsn_code) ? $duplicate_product->hsn_code : null, ['class' => 'form-control', 'placeholder' => __('lang_v1.hsn_code')]); !!}
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('drug_schedule', __('lang_v1.drug_schedule') . ':') !!}
                {!! Form::select('drug_schedule', $drug_schedules, !empty($duplicate_product->drug_schedule) ? $duplicate_product->drug_schedule : null, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2']); !!}
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('dosage_form', __('lang_v1.dosage_form') . ':') !!}
                {!! Form::select('dosage_form', $dosage_forms, !empty($duplicate_product->dosage_form) ? $duplicate_product->dosage_form : null, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2']); !!}
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('storage_condition', __('lang_v1.storage_condition') . ':') !!}
                {!! Form::select('storage_condition', $storage_conditions, !empty($duplicate_product->storage_condition) ? $duplicate_product->storage_condition : null, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2']); !!}
            </div>
        </div>

        <div class="clearfix"></div>

        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('manufacturer_id', __('lang_v1.manufacturer') . ':') !!}
                <div class="input-group">
                    {!! Form::select('manufacturer_id', $manufacturers, !empty($duplicate_product->manufacturer_id) ? $duplicate_product->manufacturer_id : null, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2']); !!}
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
                    {!! Form::select('division_id', $divisions, !empty($duplicate_product->division_id) ? $duplicate_product->division_id : null, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2']); !!}
                    <span class="input-group-btn">
                        <button type="button" @if(!auth()->user()->can('division.create')) disabled @endif class="btn btn-default bg-white btn-flat btn-modal" data-href="{{action([\App\Http\Controllers\DivisionController::class, 'create'], ['quick_add' => true])}}" title="@lang('lang_v1.add_division')" data-container=".view_modal"><i class="fa fa-plus-circle text-primary fa-lg"></i></button>
                    </span>
                </div>
            </div>
        </div>

        {{-- 'Tags' field removed — movement tags are managed per-store
             (Superadmin → Movement Tags → Stock Min/Max Settings) and
             auto-computed every 90 days, so a product-level tag field is
             redundant. --}}

        @php
        $default_location = null;
        if(count($business_locations) == 1){
        $default_location = array_key_first($business_locations->toArray());
        }
        @endphp
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('product_locations', __('business.business_locations') . ':') !!} @show_tooltip(__('lang_v1.product_location_help'))
                {!! Form::select('product_locations[]', $business_locations, $default_location, ['class' => 'form-control select2', 'multiple', 'id' => 'product_locations']); !!}
            </div>
        </div>


        <div class="clearfix"></div>

        <div class="col-sm-4">
            <div class="form-group">
                <br>
                <label>
                    {!! Form::checkbox('enable_stock', 1, !empty($duplicate_product) ? $duplicate_product->enable_stock : true, ['class' => 'input-icheck', 'id' => 'enable_stock']); !!} <strong>@lang('product.manage_stock')</strong>
                </label>@show_tooltip(__('tooltip.enable_stock')) <p class="help-block"><i>@lang('product.enable_stock_help')</i></p>
            </div>
        </div>

        {{-- 'Prescription required' checkbox removed — it is now derived
             automatically from the Drug Schedule (H, H1, X require a
             prescription). Set on save in ProductController. --}}

        <div class="col-sm-4">
            <div class="form-group">
                <br>
                <label>
                    {!! Form::checkbox('can_be_purchased', 1, !empty($duplicate_product) ? $duplicate_product->can_be_purchased : true, ['class' => 'input-icheck']); !!} <strong>@lang('lang_v1.can_be_purchased')</strong>
                </label>
            </div>
        </div>

        <div class="clearfix"></div>

        <div class="col-sm-4">
            <div class="form-group">
                <br>
                <label>
                    {!! Form::checkbox('can_be_stored', 1, !empty($duplicate_product) ? $duplicate_product->can_be_stored : true, ['class' => 'input-icheck']); !!} <strong>@lang('lang_v1.can_be_stored')</strong>
                </label>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                <br>
                <label>
                    {!! Form::checkbox('can_be_sold', 1, !empty($duplicate_product) ? $duplicate_product->can_be_sold : true, ['class' => 'input-icheck']); !!} <strong>@lang('lang_v1.can_be_sold')</strong>
                </label>
            </div>
        </div>

        {{-- Alert quantity is no longer entered separately: the low-stock
             / reorder threshold now comes from the per-store Min stock
             (variation_location_details.min_quantity), set via Superadmin
             → Movement Tags → Stock Min/Max Settings (or the 90-day auto
             calc). Kept as a hidden field so the value round-trips and
             the enable-stock toggle JS (#alert_quantity_div / #alert_quantity)
             still has its targets. --}}
        <div id="alert_quantity_div" style="display:none;">
            {!! Form::hidden('alert_quantity', !empty($duplicate_product->alert_quantity) ? @format_quantity($duplicate_product->alert_quantity) : null, ['id' => 'alert_quantity']); !!}
        </div>
        @if(!empty($common_settings['enable_product_warranty']))
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('warranty_id', __('lang_v1.warranty') . ':') !!}
                {!! Form::select('warranty_id', $warranties, null, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select')]); !!}
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
        <div class="col-sm-8 mb-5">
            <div class="form-group">
                <div class="row">
                    <div class="col-sm-8 product-description-label">
                        {!! Form::label('product_description', __('lang_v1.product_description') . ':') !!}
                    </div> 
                </div>
                {!! Form::textarea('product_description', !empty($duplicate_product->product_description) ? $duplicate_product->product_description : null, ['class' => 'form-control']); !!}
            </div>
        </div>
        <div class="col-sm-4">
            <div class="form-group">
               
                <div class="row">
                    <div class="col-sm-6 image-label">
                    {!! Form::label('image', __('lang_v1.product_image') . ':') !!}
                    </div> 
                </div>
                {!! Form::file('image', ['id' => 'upload_image', 'accept' => 'image/*',
                'required' => $is_image_required, 'class' => 'upload-element']); !!}
                <small>
                    <p class="help-block">@lang('purchase.max_file_size', ['size' => (config('constants.document_size_limit') / 1000000)]) <br> @lang('lang_v1.aspect_ratio_should_be_1_1')</p>
                </small>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="form-group">
            {!! Form::label('product_brochure', __('lang_v1.product_brochure') . ':') !!}
            {!! Form::file('product_brochure', ['id' => 'product_brochure', 'accept' => implode(',', array_keys(config('constants.document_upload_mimes_types')))]); !!}
            <small>
                <p class="help-block">
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
                    {!! Form::label('expiry_period', __('product.expires_in') . ':') !!}<br>
                    {!! Form::text('expiry_period', !empty($duplicate_product->expiry_period) ? @num_format($duplicate_product->expiry_period) : $expiry_period, ['class' => 'form-control pull-left input_number',
                    'placeholder' => __('product.expiry_period'), 'style' => 'width:60%;']); !!}
                    {!! Form::select('expiry_period_type', ['months'=>__('product.months'), 'days'=>__('product.days'), '' =>__('product.not_applicable') ], !empty($duplicate_product->expiry_period_type) ? $duplicate_product->expiry_period_type : 'months', ['class' => 'form-control select2 pull-left', 'style' => 'width:40%;', 'id' => 'expiry_period_type']); !!}
                </div>
            </div>
        </div>
        @endif

        <div class="col-sm-4">
            <div class="form-group">
                <br>
                <label>
                    {!! Form::checkbox('enable_sr_no', 1, !(empty($duplicate_product)) ? $duplicate_product->enable_sr_no : false, ['class' => 'input-icheck']); !!} <strong>@lang('lang_v1.enable_imei_or_sr_no')</strong>
                </label> @show_tooltip(__('lang_v1.tooltip_sr_no'))
            </div>
        </div>

        {{-- 'Not for selling' checkbox removed — it is the inverse of
             'Can be sold' above. not_for_selling is derived from
             can_be_sold on save (not_for_selling = can_be_sold ? 0 : 1). --}}

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
                {!! Form::label('rack_' . $id, $location . ':') !!}

                @if(session('business.enable_racks'))
                {!! Form::text('product_racks[' . $id . '][rack]', !empty($rack_details[$id]['rack']) ? $rack_details[$id]['rack'] : null, ['class' => 'form-control', 'id' => 'rack_' . $id,
                'placeholder' => __('lang_v1.rack')]); !!}
                @endif

                @if(session('business.enable_row'))
                {!! Form::text('product_racks[' . $id . '][row]', !empty($rack_details[$id]['row']) ? $rack_details[$id]['row'] : null, ['class' => 'form-control', 'placeholder' => __('lang_v1.row')]); !!}
                @endif

                @if(session('business.enable_position'))
                {!! Form::text('product_racks[' . $id . '][position]', !empty($rack_details[$id]['position']) ? $rack_details[$id]['position'] : null, ['class' => 'form-control', 'placeholder' => __('lang_v1.position')]); !!}
                @endif
            </div>
        </div>
        @endforeach
        @endif

        @php
        $custom_labels = json_decode(session('business.custom_labels'), true);
        $product_custom_fields = !empty($custom_labels['product']) ? $custom_labels['product'] : [];
        $product_cf_details = !empty($custom_labels['product_cf_details']) ? $custom_labels['product_cf_details'] : [];

        @endphp
        <!--custom fields-->
        <div class="clearfix"></div>

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
                        
                            <input type="{{$cf_type}}" name="{{$db_field_name}}" id="{{$db_field_name}}" value="{{!empty($duplicate_product->$db_field_name) ? $duplicate_product->$db_field_name : null}}" class="form-control" placeholder="{{$cf}}">

                        @elseif($cf_type == 'dropdown')
                            <!-- {!! Form::select($db_field_name, $dropdown, !empty($duplicate_product->$db_field_name) ? $duplicate_product->$db_field_name : null, ['placeholder' => $cf, 'class' => 'form-control select2']); !!} -->
                            <select name="{{ $db_field_name }}" id="{{ $db_field_name }}" class="form-control select2">
                                <option value="">{{ $cf }}</option>
                                @foreach($dropdown as $option)
                                    <option value="{{ $option }}" @if(!empty($duplicate_product->$db_field_name) && $option == $duplicate_product->$db_field_name) selected @endif>
                                        {{ $option }}
                                    </option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                </div>
            @endif
        @endforeach

        <!--custom fields-->
        <div class="clearfix"></div>
        @include('layouts.partials.module_form_part')
    </div>
    @endcomponent

    @component('components.widget', ['class' => 'box-primary'])
    <div class="row">

        <div class="col-sm-4 @if(!session('business.enable_price_tax')) hide @endif">
            <div class="form-group">
                {!! Form::label('tax', __('product.applicable_tax') . ':') !!}
                {!! Form::select('tax', $taxes, !empty($duplicate_product->tax) ? $duplicate_product->tax : null, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2'], $tax_attributes); !!}
            </div>
        </div>

        <div class="col-sm-4 @if(!session('business.enable_price_tax')) hide @endif">
            <div class="form-group">
                {!! Form::label('tax_type', __('product.selling_price_tax_type') . ':*') !!}
                {!! Form::select('tax_type', ['inclusive' => __('product.inclusive'), 'exclusive' => __('product.exclusive')], !empty($duplicate_product->tax_type) ? $duplicate_product->tax_type : 'exclusive',
                ['class' => 'form-control select2', 'required']); !!}
            </div>
        </div>

        <div class="clearfix"></div>

        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('type', __('product.product_type') . ':*') !!} @show_tooltip(__('tooltip.product_type'))
                {!! Form::select('type', $product_types, !empty($duplicate_product->type) ? $duplicate_product->type : null, ['class' => 'form-control select2',
                'required', 'data-action' => !empty($duplicate_product) ? 'duplicate' : 'add', 'data-product_id' => !empty($duplicate_product) ? $duplicate_product->id : '0']); !!}
            </div>
        </div>

        <div class="form-group col-sm-12" id="product_form_part">
            @include('product.partials.single_product_form_part', ['profit_percent' => $default_profit_percent])
        </div>

        <input type="hidden" id="variation_counter" value="1">
        <input type="hidden" id="default_profit_percent" value="{{ $default_profit_percent }}">

    </div>
    @endcomponent
    <div class="row">
        <div class="col-sm-12">
            <input type="hidden" name="submit_type" id="submit_type">
            <div class="text-center">
                <div class="btn-group">
                    @if($selling_price_group_count)
                    <button type="submit" value="submit_n_add_selling_prices" class="tw-dw-btn tw-dw-btn-warning tw-dw-btn-lg tw-text-white submit_product_form">@lang('lang_v1.save_n_add_selling_price_group_prices')</button>
                    @endif

                    @can('product.opening_stock')
                    <button id="opening_stock_button" @if(!empty($duplicate_product) && $duplicate_product->enable_stock == 0) disabled @endif type="submit" value="submit_n_add_opening_stock" class="tw-dw-btn tw-dw-btn-lg tw-text-white bg-purple submit_product_form">@lang('lang_v1.save_n_add_opening_stock')</button>
                    @endcan

                    <button type="submit" value="save_n_add_another" class="tw-dw-btn tw-dw-btn-lg bg-maroon submit_product_form">@lang('lang_v1.save_n_add_another')</button>

                    <button type="submit" value="submit" class="tw-dw-btn tw-dw-btn-primary tw-dw-btn-lg tw-text-white submit_product_form">@lang('messages.save')</button>
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
    $(document).ready(function() {
        __page_leave_confirmation('#product_add_form');
        onScan.attachTo(document, {
            suffixKeyCodes: [13], // enter-key expected at the end of a scan
            reactToPaste: true, // Compatibility to built-in scanners in paste-mode (as opposed to keyboard-mode)
            onScan: function(sCode, iQty) {
                $('input#sku').val(sCode);
            },
            onScanError: function(oDebug) {
                console.log(oDebug);
            },
            minLength: 2,
            ignoreIfFocusOn: ['input', '.form-control']
            // onKeyDetect: function(iKeyCode){ // output all potentially relevant key events - great for debugging!
            //     console.log('Pressed: ' + iKeyCode);
            // }
        });

        // Keep the Default sell / purchase sub-unit dropdowns in
        // sync with the user's choices in the Related Sub Units
        // multi-select. Only units ticked there (plus the product's
        // own base unit) are valid defaults.
        function refreshDefaultSubUnitOptions() {
            var selected = $('#sub_unit_ids').val() || [];
            var baseId = $('select[name="unit_id"]').val();
            var allIds = selected.slice();
            if (baseId && allIds.indexOf(baseId) === -1) {
                allIds.unshift(baseId);
            }

            var $sell = $('#default_sell_sub_unit_id');
            var $purchase = $('#default_purchase_sub_unit_id');
            // Prefer the user's current selection; fall back to the
            // data-initial injected when duplicating a product, so
            // the source product's defaults carry over.
            var sellVal = $sell.val() || $sell.data('initial');
            var purchaseVal = $purchase.val() || $purchase.data('initial');

            $sell.empty().append('<option value="">@lang("messages.please_select")</option>');
            $purchase.empty().append('<option value="">@lang("messages.please_select")</option>');

            // Build [{id,label,multiplier}] from the rendered
            // <option>s of #sub_unit_ids (they already carry the
            // data attributes we need via getSubUnits endpoint).
            var optsById = {};
            $('#sub_unit_ids option').each(function () {
                optsById[this.value] = $(this).text();
            });
            // Also include the base unit's text.
            $('select[name="unit_id"] option').each(function () {
                if (this.value) {
                    optsById[this.value] = $(this).text();
                }
            });

            allIds.forEach(function (id) {
                var label = optsById[id] || id;
                $sell.append('<option value="' + id + '">' + label + '</option>');
                $purchase.append('<option value="' + id + '">' + label + '</option>');
            });

            // Preserve the saved value across re-renders even if the
            // user removed its unit from the sub_unit_ids multi-select.
            // We add a fallback <option> so the form does not silently
            // drop the saved pick and re-validate to null on save.
            [['sell', $sell, sellVal], ['purchase', $purchase, purchaseVal]].forEach(function (entry) {
                var kind = entry[0], $el = entry[1], val = entry[2];
                if (!val) return;
                if (allIds.indexOf(val.toString()) !== -1) return;
                var label = (optsById[val] || (kind === 'sell' ? 'Saved default (sell)' : 'Saved default (purchase)'));
                $el.append('<option value="' + val + '">' + label + '</option>');
            });

            if (sellVal && ($sell.find('option[value="' + sellVal + '"]').length || allIds.indexOf(sellVal.toString()) !== -1)) {
                $sell.val(sellVal);
            }
            if (purchaseVal && ($purchase.find('option[value="' + purchaseVal + '"]').length || allIds.indexOf(purchaseVal.toString()) !== -1)) {
                $purchase.val(purchaseVal);
            }

            // Rebuild the sellable/purchasable unit whitelists from the
            // same allowed-unit list, preserving current selections (or
            // the duplicate-product values injected via data-initial on
            // first load).
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
                allIds.forEach(function (id) {
                    var label = optsById[id] || id;
                    var selected_attr = current.indexOf(id.toString()) !== -1 ? ' selected' : '';
                    $el.append('<option value="' + id + '"' + selected_attr + '>' + label + '</option>');
                });
                if (current.length) {
                    $el.removeData('initial').removeAttr('data-initial');
                }
                $el.trigger('change.select2');
            });

            // Sync the select2 rendered text without touching the
            // form's dirty-tracking.
            $sell.trigger('change.select2');
            $purchase.trigger('change.select2');
        }

        // Re-run on sub-unit and base-unit changes.
        $(document).on('change', '#sub_unit_ids, select[name="unit_id"]', function () {
            // Small delay so the select2 multi-select commit fires first.
            setTimeout(refreshDefaultSubUnitOptions, 30);
        });

        // Initial population once sub_unit_ids has been filled by
        // the existing getSubUnits AJAX call.
        var __defaultSubUnitWatcher = setInterval(function () {
            if ($('#sub_unit_ids option').length > 0) {
                clearInterval(__defaultSubUnitWatcher);
                refreshDefaultSubUnitOptions();
            }
        }, 100);
        // Stop trying after 5s — fallback.
        setTimeout(function () { clearInterval(__defaultSubUnitWatcher); }, 5000);
    });
</script>
@endsection