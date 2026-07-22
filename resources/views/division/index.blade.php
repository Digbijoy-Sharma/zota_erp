@extends('layouts.app')
@section('title', __('lang_v1.division'))

@section('content')

    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.division')
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('lang_v1.manage_divisions')</small>
        </h1>
    </section>

    <section class="content">
        @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.division')])
            @can('division.create')
                @slot('tool')
                    <div class="box-tools">
                        <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full btn-modal pull-right"
                            data-href="{{action([\App\Http\Controllers\DivisionController::class, 'create']) }}"
                            data-container=".divisions_modal">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M12 5l0 14" />
                                <path d="M5 12l14 0" />
                            </svg> @lang('messages.add')
                        </a>
                    </div>
                @endslot
            @endcan
            @can('division.view')
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="divisions_table">
                        <thead>
                            <tr>
                                <th>@lang('lang_v1.division_name')</th>
                                <th>@lang('brand.note')</th>
                                <th class="not-export">@lang('messages.action')</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            @endcan
        @endcomponent

        <div class="modal fade divisions_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
        </div>

    </section>

@endsection

@section('javascript')
<script>
    $(document).ready(function() {
        var divisions_table = $('#divisions_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: '/divisions',
            columns: [
                { data: 'name', name: 'name' },
                { data: 'description', name: 'description' },
                { data: 'action', name: 'action', orderable: false, searchable: false },
            ],
        });

        $(document).on('click', 'button.edit_division_button', function() {
            var url = $(this).data('href');
            $.ajax({
                url: url,
                dataType: 'html',
                success: function(result) {
                    $('.divisions_modal').html(result).modal('show');
                },
            });
        });

        $(document).on('click', 'button.delete_division_button', function() {
            var url = $(this).data('href');
            swal({
                title: LANG.sure,
                icon: 'warning',
                buttons: true,
                dangerMode: true,
            }).then(function(willDelete) {
                if (willDelete) {
                    $.ajax({
                        method: 'DELETE',
                        url: url,
                        dataType: 'json',
                        success: function(result) {
                            if (result.success) {
                                toastr.success(result.msg);
                                divisions_table.ajax.reload();
                            } else {
                                toastr.error(result.msg);
                            }
                        },
                    });
                }
            });
        });

        $(document).on('submit', 'form#division_add_form', function(e) {
            e.preventDefault();
            var form = $(this);
            $.ajax({
                method: 'POST',
                url: form.attr('action'),
                dataType: 'json',
                data: form.serialize(),
                beforeSend: function() {
                    __disable_submit_button(form.find('button[type="submit"]'));
                },
                success: function(result) {
                    if (result.success) {
                        $('div.divisions_modal').modal('hide');
                        toastr.success(result.msg);
                        divisions_table.ajax.reload();
                    } else {
                        toastr.error(result.msg);
                    }
                },
            });
        });

        $(document).on('submit', 'form#division_edit_form', function(e) {
            e.preventDefault();
            var form = $(this);
            $.ajax({
                method: 'POST',
                url: form.attr('action'),
                dataType: 'json',
                data: form.serialize(),
                beforeSend: function() {
                    __disable_submit_button(form.find('button[type="submit"]'));
                },
                success: function(result) {
                    if (result.success) {
                        $('div.divisions_modal').modal('hide');
                        toastr.success(result.msg);
                        divisions_table.ajax.reload();
                    } else {
                        toastr.error(result.msg);
                    }
                },
            });
        });
    });
</script>
@endsection
