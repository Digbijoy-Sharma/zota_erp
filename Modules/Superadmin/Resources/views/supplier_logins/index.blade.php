@extends('layouts.app')
@section('title', __('superadmin::lang.supplier_logins'))

@section('content')
    @include('superadmin::layouts.nav')

    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
            @lang('superadmin::lang.supplier_logins')
            <small class="tw-text-sm tw-text-gray-700 tw-font-semibold">@lang('superadmin::lang.supplier_logins_help')</small>
        </h1>
    </section>

    <section class="content">
        @php
            $suppliers_without_login = $suppliers->filter(function ($s) use ($logins) {
                return ! isset($logins[$s->id]);
            });
        @endphp

        {{-- Create a new supplier login --}}
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">@lang('superadmin::lang.create_supplier_login')</h3></div>
            <div class="box-body">
                @if($suppliers_without_login->isEmpty())
                    <p class="text-muted">@lang('superadmin::lang.all_suppliers_have_logins')</p>
                @else
                    {!! Form::open(['url' => action([\Modules\Superadmin\Http\Controllers\SupplierLoginController::class, 'store']), 'method' => 'post', 'class' => 'form-horizontal']) !!}
                        <div class="row">
                            <div class="col-sm-3">
                                <div class="form-group">
                                    {!! Form::label('contact_id', __('purchase.supplier') . ':') !!}
                                    <select name="contact_id" class="form-control" required>
                                        <option value="">@lang('messages.please_select')</option>
                                        @foreach($suppliers_without_login as $s)
                                            <option value="{{ $s->id }}">{{ $s->name }} @if($s->supplier_business_name) ({{ $s->supplier_business_name }}) @endif</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-2">
                                <div class="form-group">
                                    {!! Form::label('username', __('business.username') . ':') !!}
                                    {!! Form::text('username', null, ['class' => 'form-control', 'required']) !!}
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-group">
                                    {!! Form::label('email', __('business.email') . ':') !!}
                                    {!! Form::email('email', null, ['class' => 'form-control']) !!}
                                </div>
                            </div>
                            <div class="col-sm-2">
                                <div class="form-group">
                                    {!! Form::label('password', __('business.password') . ':') !!}
                                    {!! Form::password('password', ['class' => 'form-control', 'required']) !!}
                                </div>
                            </div>
                            <div class="col-sm-2">
                                <div class="form-group">
                                    {!! Form::label('password_confirmation', __('business.confirm_password') . ':') !!}
                                    {!! Form::password('password_confirmation', ['class' => 'form-control', 'required']) !!}
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">@lang('superadmin::lang.create_supplier_login')</button>
                    {!! Form::close() !!}
                @endif
            </div>
        </div>

        {{-- Existing supplier logins --}}
        <div class="box box-solid">
            <div class="box-header with-border"><h3 class="box-title">@lang('superadmin::lang.existing_supplier_logins')</h3></div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>@lang('purchase.supplier')</th>
                            <th>@lang('business.username')</th>
                            <th>@lang('business.email')</th>
                            <th>@lang('messages.status')</th>
                            <th>@lang('messages.action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($suppliers as $s)
                            @php $login = $logins[$s->id] ?? null; @endphp
                            @if($login)
                                <tr>
                                    <td>{{ $s->name }}</td>
                                    <td>{{ $login->username }}</td>
                                    <td>{{ $login->email }}</td>
                                    <td>
                                        @if($login->allow_login && $login->status === 'active')
                                            <span class="label label-success">@lang('superadmin::lang.active')</span>
                                        @else
                                            <span class="label label-default">@lang('superadmin::lang.disabled')</span>
                                        @endif
                                    </td>
                                    <td>
                                        {!! Form::open(['url' => action([\Modules\Superadmin\Http\Controllers\SupplierLoginController::class, 'toggle'], [$login->id]), 'method' => 'post', 'style' => 'display:inline;']) !!}
                                            <button type="submit" class="btn btn-xs {{ $login->allow_login ? 'btn-warning' : 'btn-success' }}">
                                                {{ $login->allow_login ? __('superadmin::lang.disable') : __('superadmin::lang.enable') }}
                                            </button>
                                        {!! Form::close() !!}
                                        <button type="button" class="btn btn-xs btn-default" onclick="document.getElementById('reset-{{ $login->id }}').style.display='block'">@lang('superadmin::lang.reset_password')</button>
                                        <div id="reset-{{ $login->id }}" style="display:none; margin-top:6px;">
                                            {!! Form::open(['url' => action([\Modules\Superadmin\Http\Controllers\SupplierLoginController::class, 'resetPassword'], [$login->id]), 'method' => 'post', 'class' => 'form-inline']) !!}
                                                {!! Form::password('password', ['class' => 'form-control input-sm', 'placeholder' => __('business.password'), 'required']) !!}
                                                {!! Form::password('password_confirmation', ['class' => 'form-control input-sm', 'placeholder' => __('business.confirm_password'), 'required']) !!}
                                                <button type="submit" class="btn btn-xs btn-primary">@lang('messages.save')</button>
                                            {!! Form::close() !!}
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                        @endforelse
                        @if($logins->isEmpty())
                            <tr><td colspan="5" class="text-center text-muted">@lang('superadmin::lang.no_supplier_logins')</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
