<div class="modal-dialog" role="document">
  <div class="modal-content">

    {!! Form::open(['url' => action([\App\Http\Controllers\UnitController::class, 'store']), 'method' => 'post', 'id' => $quick_add ? 'quick_add_unit_form' : 'unit_add_form' ]) !!}

    <div class="modal-header">
      <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      <h4 class="modal-title">@lang( 'unit.add_unit' )</h4>
    </div>

    <div class="modal-body">
      <div class="row">
        <div class="form-group col-sm-12">
          {!! Form::label('actual_name', __( 'unit.name' ) . ':*') !!}
            {!! Form::text('actual_name', null, ['class' => 'form-control', 'required', 'placeholder' => __( 'unit.name' )]); !!}
        </div>

        <div class="form-group col-sm-12">
          {!! Form::label('short_name', __( 'unit.short_name' ) . ':*') !!}
            {!! Form::text('short_name', null, ['class' => 'form-control', 'placeholder' => __( 'unit.short_name' ), 'required']); !!}
        </div>

        <div class="form-group col-sm-12">
          {!! Form::label('allow_decimal', __( 'unit.allow_decimal' ) . ':*') !!}
            {!! Form::select('allow_decimal', ['1' => __('messages.yes'), '0' => __('messages.no')], null, ['placeholder' => __( 'messages.please_select' ), 'required', 'class' => 'form-control']); !!}
        </div>
        @if(!$quick_add)
          <div class="form-group col-sm-12">
            <div class="form-group">
                <div class="checkbox">
                  <label>
                     {!! Form::checkbox('define_base_unit', 1, false,[ 'class' => 'toggler', 'data-toggle_id' => 'base_unit_div' ]); !!} @lang( 'lang_v1.add_as_multiple_of_base_unit' )
                  </label> @show_tooltip(__('lang_v1.multi_unit_help'))
                </div>
            </div>
          </div>
          <div class="form-group col-sm-12 hide" id="base_unit_div">
            <table class="table">
              <tr>
                <th style="vertical-align: middle;">1 <span id="unit_name">@lang('product.unit')</span></th>
                <th style="vertical-align: middle;">=</th>
                <td style="vertical-align: middle;">
                  {!! Form::text('base_unit_multiplier', null, ['class' => 'form-control input_number', 'id' => 'base_unit_multiplier', 'placeholder' => __( 'lang_v1.times_base_unit' )]); !!}</td>
                <td style="vertical-align: middle;">
                  {!! Form::select('base_unit_id', $units, null, ['placeholder' => __( 'lang_v1.select_base_unit' ), 'class' => 'form-control', 'id' => 'base_unit_id_select']); !!}
                </td>
              </tr>
            </table>

            {{-- Intermediate unit helper --}}
            @if($sub_units->count() > 0)
            <div class="well well-sm" style="margin-top: 5px; background: #f9f9f9;">
              <div class="checkbox" style="margin-top: 0;">
                <label>
                  {!! Form::checkbox('define_via_intermediate', 1, false, ['class' => 'toggle_intermediate', 'id' => 'define_via_intermediate']); !!}
                  @lang('unit.define_via_intermediate_unit')
                </label>
                @show_tooltip(__('unit.intermediate_unit_help'))
              </div>
              <div id="intermediate_unit_section" class="hide" style="margin-top: 10px;">
                <table class="table" style="margin-bottom: 5px;">
                  <tr>
                    <th style="vertical-align: middle;">1 <span class="intermediate_unit_label">@lang('product.unit')</span></th>
                    <th style="vertical-align: middle;">=</th>
                    <td style="vertical-align: middle;">
                      {!! Form::text('intermediate_multiplier', null, ['class' => 'form-control input_number', 'id' => 'intermediate_multiplier', 'placeholder' => __('unit.quantity')]); !!}
                    </td>
                    <td style="vertical-align: middle;">
                      <select name="intermediate_unit_id" id="intermediate_unit_id" class="form-control">
                        <option value="">@lang('unit.select_intermediate_unit')</option>
                        @foreach($sub_units as $su)
                          <option value="{{ $su->id }}" data-multiplier="{{ $su->base_unit_multiplier }}" data-base_unit_id="{{ $su->base_unit_id }}">{{ $su->actual_name }} ({{ $su->short_name }})</option>
                        @endforeach
                      </select>
                    </td>
                  </tr>
                </table>
                <p class="help-block text-success" id="calculated_base_multiplier_info" style="display:none;">
                  <i class="fa fa-info-circle"></i> <span id="calc_info_text"></span>
                </p>
              </div>
            </div>
            @endif
          </div>
        @endif
      </div>

    </div>

    <div class="modal-footer">
      <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang( 'messages.save' )</button>
      <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">@lang( 'messages.close' )</button>
    </div>

    {!! Form::close() !!}

  </div><!-- /.modal-content -->
</div><!-- /.modal-dialog -->