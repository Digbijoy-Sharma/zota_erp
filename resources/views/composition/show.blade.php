@extends('layouts.app')
@section('title', __('composition.view_composition'))

@section('content')
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ $composition->name }}
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('composition.view_composition')</small>
        </h1>
        <a href="{{ action([\App\Http\Controllers\CompositionController::class, 'index']) }}"
           class="tw-dw-btn tw-dw-btn-secondary tw-text-white">
            <i class="fa fa-arrow-left"></i> @lang('messages.back')
        </a>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="box box-solid">
            <div class="box-header with-border">
                <h3 class="box-title">@lang('composition.composition_details')</h3>
            </div>
            <div class="box-body">
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <th style="width: 30%;">@lang('composition.composition_name')</th>
                            <td><strong>{{ $composition->name }}</strong></td>
                        </tr>
                        <tr>
                            <th>@lang('composition.salts')</th>
                            <td>
                                @if ($composition->salts->count() > 0)
                                    <ol>
                                        @foreach ($composition->salts as $salt)
                                            <li>{{ $salt->name }}</li>
                                        @endforeach
                                    </ol>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>@lang('messages.created_at')</th>
                            <td>{{ $composition->created_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
