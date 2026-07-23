@extends('layouts.app')
@section('title', __('superadmin::lang.superadmin') . ' | Business')

@section('content')
@include('superadmin::layouts.nav')
<!-- Main content -->
<section class="content">

	<div class="box box-solid">
        <div class="box-header">
        	<h3 class="box-title">@lang( 'superadmin::lang.add_new_business' ) <small>(@lang( 'superadmin::lang.add_business_help' ))</small></h3>
        </div>

        <div class="box-body">
                {!! Form::open(['url' => action([\Modules\Superadmin\Http\Controllers\BusinessController::class, 'store']), 'method' => 'post', 'id' => 'business_register_form','files' => true ]) !!}
                    @include('business.partials.register_form')
                    <div class="clearfix"></div>
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('store_unique_number', __('superadmin::lang.store_unique_number') . ':') !!}
                            {!! Form::text('store_unique_number', null, ['class' => 'form-control', 'placeholder' => __('superadmin::lang.store_unique_number_placeholder')]); !!}
                            <p class="help-block">@lang('superadmin::lang.store_unique_number_help')</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('auto_po_frequency_days', __('superadmin::lang.auto_po_frequency_days') . ':') !!}
                            {!! Form::number('auto_po_frequency_days', null, ['class' => 'form-control', 'min' => 1, 'max' => 30, 'placeholder' => __('superadmin::lang.inherit')]); !!}
                            <div class="checkbox">
                                <label>
                                    {!! Form::checkbox('apply_po_freq_chain_wide', 1, false) !!}
                                    @lang('superadmin::lang.apply_po_freq_chain_wide')
                                </label>
                            </div>
                            <p class="help-block">@lang('superadmin::lang.auto_po_frequency_help')</p>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                    <div class="col-md-12"><hr></div>
                    <div class="col-md-12">
                        <div class="form-group">
                            {!! Form::label('owner_source', __('superadmin::lang.assign_existing_user') . ':') !!}
                            <div class="radio" style="margin-top: 0;">
                                <label>
                                    {!! Form::radio('owner_source', 'new', true, ['id' => 'owner_source_new']) !!}
                                    @lang('superadmin::lang.create_new_owner')
                                </label>
                                <label style="margin-left: 20px;">
                                    {!! Form::radio('owner_source', 'existing', false, ['id' => 'owner_source_existing']) !!}
                                    @lang('superadmin::lang.assign_existing_user')
                                </label>
                            </div>
                            <p class="help-block">@lang('superadmin::lang.select_precreated_user')</p>
                        </div>
                    </div>
                    <div class="col-md-12" id="existing_user_section" style="display: none;">
                        <div class="form-group">
                            {!! Form::label('precreated_user_id', __('superadmin::lang.precreated_users') . ':') !!}
                            <select class="form-control select2" id="precreated_user_id" style="width: 100%;" name="precreated_user_id">
                                <option value="">@lang('messages.please_select')</option>
                                @foreach($precreated_users ?? [] as $pu)
                                    <option value="{{ $pu->id }}"
                                        data-surname="{{ $pu->surname }}"
                                        data-first-name="{{ $pu->first_name }}"
                                        data-last-name="{{ $pu->last_name }}"
                                        data-username="{{ $pu->username }}"
                                        data-email="{{ $pu->email }}">{{ trim(($pu->surname ?? '').' '.($pu->first_name ?? '').' '.($pu->last_name ?? '').' ('.$pu->username.')') }}</option>
                                @endforeach
                            </select>
                            <p class="help-block">
                                <a href="{{ action([\App\Http\Controllers\ManageUserController::class, 'create']) }}" target="_blank">
                                    <i class="fa fa-plus"></i> @lang('superadmin::lang.precreate_user')
                                </a>
                                <span class="text-muted"> — @lang('superadmin::lang.precreate_user_link_hint')</span>
                            </p>
                            <div class="alert alert-info" id="existing_user_info" style="display:none; margin-top: 8px; padding: 6px 10px;">
                                <i class="fa fa-info-circle"></i>
                                <span id="existing_user_info_text"></span>
                            </div>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                    <div class="col-md-12"><hr></div>
                    <div class="col-md-12">
                        <div class="form-group">
                            {!! Form::label('supplier_ids', __( 'superadmin::lang.assign_common_suppliers' ) . ':') !!}
                            @if (empty($common_suppliers) || (is_countable($common_suppliers) ? count($common_suppliers) === 0 : (method_exists($common_suppliers, 'isEmpty') && $common_suppliers->isEmpty())))
                                <div class="alert alert-warning" style="padding: 6px 10px;">
                                    <i class="fa fa-info-circle"></i>
                                    No suppliers available yet. Add suppliers in
                                    <a href="{{ action([\App\Http\Controllers\ContactController::class, 'index']) }}" target="_blank">
                                        Contacts &rarr; Suppliers
                                    </a>
                                    (in the super admin's business) first.
                                </div>
                            @else
                                {!! Form::select('supplier_ids[]', $common_suppliers, null, [
                                    'class' => 'form-control select2',
                                    'multiple' => 'multiple',
                                    'id' => 'supplier_ids_create',
                                ]); !!}
                            @endif
                            <p class="help-block">@lang('superadmin::lang.assign_common_suppliers_help')</p>
                        </div>
                    </div>
                    <div class="col-md-12"><hr></div>
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('package_id', __( 'superadmin::lang.subscription_packages' ) . ':') !!}
                            {!! Form::select('package_id', $packages, null, ['class' => 'form-control', 'placeholder' => __( 'messages.please_select' ) ]); !!}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('paid_via', __( 'superadmin::lang.paid_via' ) . ':') !!}
                            {!! Form::select('paid_via', $gateways, null, ['class' => 'form-control', 'placeholder' => __( 'messages.please_select' ) ]); !!}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('payment_transaction_id', __( 'superadmin::lang.payment_transaction_id' ) . ':') !!}
                            {!! Form::text('payment_transaction_id', null, ['class' => 'form-control', 'placeholder' => __( 'superadmin::lang.payment_transaction_id' ) ]); !!}
                         </div>
                    </div>

                    {!! Form::submit(__('messages.submit'), ['class' => 'btn btn-success pull-right']) !!}
                {!! Form::close() !!}
        </div>
    </div>

    <div class="modal fade brands_modal" tabindex="-1" role="dialog" 
    	aria-labelledby="gridSystemModalLabel">
    </div>

</section>
<!-- /.content -->
@endsection


@section('javascript')
    <script type="text/javascript">
        $(document).ready(function(){
            $('.select2_register').select2();
            $('#precreated_user_id').select2({
                width: '100%',
                placeholder: 'Select a pre-created user',
                allowClear: true,
            });
            // Toggle the existing user selector based on owner_source
            function toggleOwnerSource() {
                if ($('#owner_source_existing').is(':checked')) {
                    $('#existing_user_section').show();
                    $('#precreated_user_id').attr('required', 'required');
                    // The new owner fields become optional
                    $('input[name="username"], input[name="password"], input[name="first_name"], input[name="last_name"], input[name="email"]').removeAttr('required');
                    // Hide password fields entirely - existing user keeps their password
                    $('.password-existing-only').show();
                    $('.password-new-only').hide();
                    // Drop the remote uniqueness checks for the owner
                    // fields: those belong to the pre-created user that
                    // already exists in the DB. Without this, jQuery
                    // Validate calls /business/register/check-* and
                    // the server reports "already taken".
                    try {
                        $('input[name="username"]').rules('remove', 'remote');
                        $('input[name="email"]').rules('remove', 'remote');
                        // Clear any stale "already taken" errors left
                        // from a previous submit attempt in new-owner mode.
                        var validator = $("form#business_register_form").validate();
                        validator.resetForm();
                        $('input[name="username"], input[name="email"]').removeClass('error');
                        $('label.error[for="username"], label.error[for="email"]').remove();
                    } catch (e) { /* validator not yet initialised */ }
                    populateOwnerFromSelected();
                } else {
                    $('#existing_user_section').hide();
                    $('#precreated_user_id').removeAttr('required').val(null).trigger('change');
                    $('input[name="username"], input[name="password"], input[name="first_name"], input[name="last_name"], input[name="email"]').attr('required', 'required');
                    $('.password-existing-only').hide();
                    $('.password-new-only').show();
                    // Re-attach the remote uniqueness checks so a brand
                    // new owner still gets validated normally.
                    try {
                        $('input[name="username"]').rules('add', {
                            remote: {
                                url: "/business/register/check-username",
                                type: "post",
                                data: { username: function() { return $('#username').val(); } }
                            }
                        });
                        $('input[name="email"]').rules('add', {
                            remote: {
                                url: "/business/register/check-email",
                                type: "post",
                                data: { email: function() { return $('#email').val(); } }
                            }
                        });
                    } catch (e) { /* validator not yet initialised */ }
                    clearOwnerFromSelected();
                }
            }

            // Populate Owner Information fields from the data-attributes
            // of the selected pre-created user. The fields are then
            // shown as the "Business owner details" of the new business.
            function populateOwnerFromSelected() {
                var $opt = $('#precreated_user_id').find('option:selected');
                if (! $opt.length || ! $opt.val()) {
                    clearOwnerFromSelected();
                    return;
                }
                $('input[name="surname"]').val($opt.data('surname') || '').prop('readonly', true);
                $('input[name="first_name"]').val($opt.data('first-name') || '').prop('readonly', true);
                $('input[name="last_name"]').val($opt.data('last-name') || '').prop('readonly', true);
                $('input[name="username"]').val($opt.data('username') || '').prop('readonly', true);
                $('input[name="email"]').val($opt.data('email') || '').prop('readonly', true);
                $('input[name="password"]').val('').prop('readonly', true);
                $('input[name="confirm_password"]').val('').prop('readonly', true);
                // Show a small note about the source
                var name = ($opt.data('first-name') || '') + ' ' + ($opt.data('last-name') || '');
                name = name.trim() || $opt.data('username') || '';
                $('#existing_user_info_text').text(
                    'Owner fields filled from the pre-created user: ' + name + '. The existing password will be kept.'
                );
                $('#existing_user_info').show();
            }

            // Reset the Owner Information fields back to a normal
            // "create new owner" state.
            function clearOwnerFromSelected() {
                $('input[name="surname"], input[name="first_name"], input[name="last_name"], input[name="username"], input[name="email"], input[name="password"], input[name="confirm_password"]')
                    .val('').prop('readonly', false);
                $('#existing_user_info').hide();
            }

            $('input[name="owner_source"]').change(toggleOwnerSource);
            $('#precreated_user_id').on('change', function() {
                if ($('#owner_source_existing').is(':checked')) {
                    populateOwnerFromSelected();
                }
            });
            $('#supplier_ids_create').select2({
                width: '100%',
                placeholder: 'Select suppliers to assign to this business',
                allowClear: true,
            });
            $("form#business_register_form").validate({
                errorPlacement: function(error, element) {
                    if(element.parent('.input-group').length) {
                        error.insertAfter(element.parent());
                    } else {
                        error.insertAfter(element);
                    }
                },
                rules: {
                    name: "required",
                    email: {
                        email: true,
                        remote: {
                            url: "/business/register/check-email",
                            type: "post",
                            data: {
                                email: function() {
                                    return $( "#email" ).val();
                                }
                            }
                        }
                    },
                    password: {
                        required: true,
                        minlength: 5
                    },
                    confirm_password: {
                        equalTo: "#password"
                    },
                    paid_via: {
                        required: function(element){
                                return $('#package_id').val() != '';
                            }
                    },
                    username: {
                        required: true,
                        minlength: 4,
                        remote: {
                            url: "/business/register/check-username",
                            type: "post",
                            data: {
                                username: function() {
                                    return $( "#username" ).val();
                                }
                            }
                        }
                    }
                },
                messages: {
                    name: LANG.specify_business_name,
                    password: {
                        minlength: LANG.password_min_length,
                    },
                    confirm_password: {
                        equalTo: LANG.password_mismatch
                    },
                    username: {
                        remote: LANG.invalid_username
                    },
                    email: {
                        remote: '{{ __("validation.unique", ["attribute" => __("business.email")]) }}'
                    }
                }
            });
            // Run the owner-source toggle AFTER the validator is wired
            // up so the initial render can also drop the remote
            // uniqueness rules when the form is opened directly into
            // "Assign an existing pre-created user" mode.
            toggleOwnerSource();

            $("#business_logo").fileinput({'showUpload':false, 'showPreview':false, 'browseLabel': LANG.file_browse_label, 'removeLabel': LANG.remove});
        });
    </script>
@endsection