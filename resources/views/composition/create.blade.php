@extends('layouts.app')
@section('title', __('composition.add_composition'))

@section('content')
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('composition.add_composition')</h1>
    </section>

    <!-- Main content -->
    <section class="content">
        {!! Form::open(['url' => action([\App\Http\Controllers\CompositionController::class, 'store']), 'method' => 'post', 'id' => 'composition_form']) !!}
        <div class="box box-solid">
            <div class="box-body">
                <div class="row">
                    <div class="col-sm-12">
                        <div class="form-group">
                            {!! Form::label('composition_name_display', __('composition.composition_name') . ':') !!}
                            {!! Form::text('composition_name_display', '', ['class' => 'form-control', 'id' => 'composition_name_display', 'readonly' => 'readonly', 'placeholder' => __('composition.composition_name_placeholder')]); !!}
                            <p class="help-block">@lang('composition.auto_generated_help')</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-12">
                        <label>@lang('composition.salts')</label>
                        <table class="table table-bordered" id="salts_table">
                            <thead>
                                <tr>
                                    <th style="width: 90%;">@lang('composition.salt_name')</th>
                                    <th style="width: 10%;">@lang('messages.action')</th>
                                </tr>
                            </thead>
                            <tbody id="salts_body">
                                <tr class="salt-row">
                                    <td>
                                        {!! Form::text('salts[]', '', ['class' => 'form-control salt-input', 'placeholder' => __('composition.salt_name_placeholder'), 'required']); !!}
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-xs remove-salt">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <button type="button" id="add_salt" class="btn btn-primary btn-sm">
                            <i class="fa fa-plus"></i> @lang('composition.add_salt')
                        </button>
                        <p class="help-block">@lang('composition.salts_help')</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="box box-solid">
            <div class="box-body">
                <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white tw-float-right">
                    @lang('messages.save')
                </button>
                <a href="{{ action([\App\Http\Controllers\CompositionController::class, 'index']) }}"
                   class="tw-dw-btn tw-dw-btn-secondary tw-text-white tw-float-right tw-mr-2">
                    @lang('messages.cancel')
                </a>
            </div>
        </div>
        {!! Form::close() !!}
    </section>
@endsection

@section('javascript')
    <script type="text/javascript">
        (function ($) {
            function refreshCompositionName() {
                var names = [];
                $('#salts_body .salt-row .salt-input').each(function () {
                    var v = $(this).val();
                    if (v && v.trim() !== '') {
                        names.push(v.trim());
                    }
                });
                $('#composition_name_display').val(names.join(' + '));
            }

            $(document).on('input', '.salt-input', function () {
                refreshCompositionName();
            });

            $(document).on('click', '#add_salt', function () {
                var placeholder = '@lang("composition.salt_name_placeholder")';
                var newRow = '<tr class="salt-row">'
                    + '<td><input type="text" name="salts[]" class="form-control salt-input" placeholder="' + placeholder + '" required /></td>'
                    + '<td><button type="button" class="btn btn-danger btn-xs remove-salt"><i class="fa fa-trash"></i></button></td>'
                    + '</tr>';
                $('#salts_body').append(newRow);
                $('#salts_body .salt-row:last .salt-input').focus();
                refreshCompositionName();
            });

            $(document).on('click', '.remove-salt', function () {
                if ($('#salts_body .salt-row').length <= 1) {
                    toastr.warning('@lang("composition.at_least_one_salt_required")');
                    return;
                }
                $(this).closest('tr').remove();
                refreshCompositionName();
            });

            // Reject submitting the auto-generated name as a real field.
            $('#composition_form').on('submit', function () {
                $('#composition_name_display').prop('disabled', true);
            });

            // Submit via AJAX so the controller's JSON response is handled in-page
            // (success toast + redirect to list) instead of the browser rendering
            // the raw JSON.
            $(document).ready(function () {
                $('#composition_form').on('submit', function (e) {
                    // If the browser is doing a native POST, abort and use AJAX.
                    e.preventDefault();
                    var $form = $(this);
                    var $btn = $form.find('button[type="submit"]');
                    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

                    $.ajax({
                        method: $form.attr('method'),
                        url: $form.attr('action'),
                        data: $form.serialize(),
                        dataType: 'json',
                        success: function (result) {
                            if (result.success) {
                                toastr.success(result.msg || '@lang("composition.added_success")');
                                window.location.href = "{{ action([\\App\\Http\\Controllers\\CompositionController::class, 'index']) }}" + '?status=created';
                            } else {
                                toastr.error(result.msg || '@lang("messages.something_went_wrong")');
                                $btn.prop('disabled', false).html('@lang("messages.save")');
                                $('#composition_name_display').prop('disabled', false);
                            }
                        },
                        error: function (xhr) {
                            var msg = '@lang("messages.something_went_wrong")';
                            try {
                                var json = JSON.parse(xhr.responseText);
                                if (json && json.msg) { msg = json.msg; }
                            } catch (e) { /* not JSON */ }
                            toastr.error(msg);
                            $btn.prop('disabled', false).html('@lang("messages.save")');
                            $('#composition_name_display').prop('disabled', false);
                        }
                    });
                });
            });
        })(jQuery);
    </script>
@endsection
