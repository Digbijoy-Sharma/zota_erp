@extends('layouts.app')
@section('title', __('composition.compositions'))

@section('content')
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('composition.compositions')
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('composition.manage_compositions')</small>
        </h1>
    </section>

    <!-- Main content -->
    <section class="content">
        @component('components.widget', ['class' => 'box-primary', 'title' => __('composition.all_compositions')])
            @can('product.create')
                @slot('tool')
                    <div class="box-tools">
                        <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right"
                           href="{{ action([\App\Http\Controllers\CompositionController::class, 'create']) }}">
                            <i class="fa fa-plus"></i> @lang('messages.add')
                        </a>
                    </div>
                @endslot
            @endcan
            @can('product.view')
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="compositions_table">
                        <thead>
                            <tr>
                                <th>@lang('composition.composition_name')</th>
                                <th>@lang('composition.salts_count')</th>
                                <th class="not-export">@lang('messages.action')</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            @endcan
        @endcomponent
    </section>
@endsection

@section('javascript')
    <script type="text/javascript">
        $(document).ready(function () {
            // Show a success alert if we were redirected here after a save/update.
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('status') === 'created') {
                toastr.success('@lang("composition.added_success")');
            } else if (urlParams.get('status') === 'updated') {
                toastr.success('@lang("composition.updated_success")');
            } else if (urlParams.get('status') === 'deleted') {
                toastr.success('@lang("composition.deleted_success")');
            }

            var table = $('#compositions_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{ action([\App\Http\Controllers\CompositionController::class, 'index']) }}",
                columnDefs: [
                    {
                        targets: 2,
                        orderable: false,
                        searchable: false,
                    },
                ],
                columns: [
                    { data: 'name', name: 'name' },
                    { data: 'salts_count', name: 'salts_count', orderable: false, searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false },
                ],
            });

            $(document).on('click', '.delete_composition_button', function (e) {
                e.preventDefault();
                var url = $(this).data('href');
                swal({
                    title: LANG.sure,
                    text: LANG.confirm_delete,
                    icon: 'warning',
                    buttons: true,
                    dangerMode: true,
                }).then((willDelete) => {
                    if (willDelete) {
                        $.ajax({
                            method: 'DELETE',
                            url: url,
                            dataType: 'json',
                            success: function (result) {
                                if (result.success) {
                                    toastr.success(result.msg);
                                    table.ajax.reload();
                                } else {
                                    toastr.error(result.msg);
                                }
                            },
                        });
                    }
                });
            });
        });
    </script>
@endsection
