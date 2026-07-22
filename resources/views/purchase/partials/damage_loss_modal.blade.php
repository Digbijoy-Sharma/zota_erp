<div class="modal fade" id="damage_loss_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">

            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">@lang('purchase.mark_damage_loss')</h4>
            </div>

            <div class="modal-body">
                <p class="text-muted small">@lang('purchase.damage_loss_help')</p>
                <input type="hidden" id="damage_loss_modal_row">
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            {!! Form::label('damage_loss_modal_qty_damaged', __('purchase.quantity_damaged') . ':') !!}
                            {!! Form::text('damage_loss_modal_qty_damaged', 0, ['id' => 'damage_loss_modal_qty_damaged', 'class' => 'form-control input_number']); !!}
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            {!! Form::label('damage_loss_modal_qty_lost', __('purchase.quantity_lost') . ':') !!}
                            {!! Form::text('damage_loss_modal_qty_lost', 0, ['id' => 'damage_loss_modal_qty_lost', 'class' => 'form-control input_number']); !!}
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-12">
                        <div class="form-group">
                            {!! Form::label('damage_loss_modal_reason', __('purchase.damage_loss_reason') . ':') !!}
                            {!! Form::select('damage_loss_modal_reason', $damageLossReasons, null, ['id' => 'damage_loss_modal_reason', 'class' => 'form-control select2', 'placeholder' => __('messages.please_select')]); !!}
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-12">
                        <div class="form-group">
                            {!! Form::label('damage_loss_modal_note', __('purchase.damage_loss_note') . ':') !!}
                            {!! Form::textarea('damage_loss_modal_note', null, ['id' => 'damage_loss_modal_note', 'class' => 'form-control', 'rows' => 2]); !!}
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" id="damage_loss_modal_save" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('messages.save')</button>
                <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">@lang('messages.close')</button>
            </div>

        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div>
