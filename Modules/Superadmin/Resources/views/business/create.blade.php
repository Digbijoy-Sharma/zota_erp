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

            $("#business_logo").fileinput({'showUpload':false, 'showPreview':false, 'browseLabel': LANG.file_browse_label, 'removeLabel': LANG.remove});
        });
    </script>
@endsection