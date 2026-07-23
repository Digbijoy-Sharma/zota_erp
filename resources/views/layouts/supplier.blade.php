<!DOCTYPE html>
<html class="tw-bg-white tw-scroll-smooth" lang="{{ app()->getLocale() }}"
    dir="{{ in_array(session()->get('user.language', config('app.locale')), config('constants.langs_rtl')) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('lang_v1.supplier_portal')) - {{ Session::get('business.name') }}</title>

    @include('layouts.partials.css')
    @yield('css')
</head>
<body class="tw-font-sans tw-antialiased tw-text-gray-900 tw-bg-gray-100 hold-transition skin-@if (!empty(session('business.theme_color'))){{ session('business.theme_color') }}@else{{ 'blue-light' }}@endif sidebar-mini">
    <div class="tw-flex thetop">
        <script type="text/javascript">
            if (localStorage.getItem("upos_sidebar_collapse") == 'true') {
                document.getElementsByTagName("body")[0].className += " sidebar-collapse";
            }
        </script>

        {{-- Currency fields used by common.js number formatting --}}
        <input type="hidden" id="__code" value="{{ session('currency')['code'] ?? '' }}">
        <input type="hidden" id="__symbol" value="{{ session('currency')['symbol'] ?? '' }}">
        <input type="hidden" id="__thousand" value="{{ session('currency')['thousand_separator'] ?? ',' }}">
        <input type="hidden" id="__decimal" value="{{ session('currency')['decimal_separator'] ?? '.' }}">
        <input type="hidden" id="__symbol_placement" value="{{ session('business.currency_symbol_placement') ?? 'before' }}">
        <input type="hidden" id="__precision" value="{{ session('business.currency_precision', 2) }}">
        <input type="hidden" id="__quantity_precision" value="{{ session('business.quantity_precision', 2) }}">

        @if (session('status') && is_array(session('status')))
            <input type="hidden" id="status_span" data-status="{{ session('status')['success'] }}"
                data-msg="{{ session('status')['msg'] }}" data-id="">
        @endif

        <!-- Left sidebar (same theme/skin as the other panels) -->
        <aside class="side-bar dava-side-bar tw-relative tw-hidden tw-h-full tw-w-64 xl:tw-w-64 lg:tw-flex lg:tw-flex-col tw-shrink-0">
            <a href="{{ route('supplier.dashboard') }}" class="dava-side-bar-header tw-shrink-0" title="@lang('lang_v1.supplier_portal')">
                <div class="dava-side-bar-logo">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M19 8h-2V3H7v5H5c-1.1 0-2 .9-2 2v9h18v-9c0-1.1-.9-2-2-2z" fill="#1F7A4D"/>
                        <path d="M11 11h2v2h-2zm0 3h2v2h-2z" fill="#fff"/>
                    </svg>
                </div>
                <div class="dava-side-bar-text">
                    <div class="dava-side-bar-name">{{ auth()->user()->first_name ?: 'Supplier' }}</div>
                    <div class="dava-side-bar-tag">@lang('lang_v1.supplier_portal')</div>
                </div>
                <div class="dava-side-bar-status" title="Online"></div>
            </a>

            <div class="tw-flex-1 tw-px-2 tw-pt-1.5 tw-pb-3 tw-overflow-y-auto" id="side-bar">
                <div class="dava-menu-section-title">@lang('lang_v1.supplier_portal')</div>
                <a href="{{ route('supplier.dashboard') }}" title=""
                    class="tw-flex tw-items-center tw-gap-2.5 tw-px-3 tw-py-1.5 tw-text-sm tw-font-normal tw-text-gray-600 tw-transition-all tw-duration-200 tw-rounded-lg tw-whitespace-nowrap theme-sidebar-hover{{ request()->is('supplier/dashboard') ? ' theme-sidebar-active' : '' }}">
                    <span class="menu-icon-wrap"><i class="fas fa-tachometer-alt tw-text-base"></i></span>
                    <span class="tw-truncate">@lang('home.dashboard')</span>
                </a>
                <a href="{{ route('supplier.purchase-orders') }}" title=""
                    class="tw-flex tw-items-center tw-gap-2.5 tw-px-3 tw-py-1.5 tw-text-sm tw-font-normal tw-text-gray-600 tw-transition-all tw-duration-200 tw-rounded-lg tw-whitespace-nowrap theme-sidebar-hover{{ request()->is('supplier/purchase-orders*') ? ' theme-sidebar-active' : '' }}">
                    <span class="menu-icon-wrap"><i class="fas fa-file-invoice tw-text-base"></i></span>
                    <span class="tw-truncate">@lang('lang_v1.purchase_order')</span>
                </a>
            </div>

            <div class="dava-side-bar-footer tw-shrink-0 tw-mt-auto">
                <span class="dava-side-bar-footer-badge">DavaIndia ERP v1.0</span>
            </div>
        </aside>

        <main class="tw-flex tw-flex-col tw-flex-1 tw-h-full tw-min-w-0 tw-bg-gray-100">
            <!-- Header (same theme bar as the other panels) -->
            <div class="tw-transition-all tw-duration-5000 tw-border-b theme-header-bg tw-shrink-0 lg:tw-h-15 tw-border-primary-500/30 no-print">
                <div class="tw-px-5 tw-py-3">
                    <div class="tw-flex tw-items-start tw-justify-between tw-gap-6 lg:tw-items-center">
                        <div class="tw-flex tw-items-center tw-gap-3">
                            <button type="button" class="small-view-button xl:tw-w-20 lg:tw-hidden tw-inline-flex tw-items-center tw-justify-center tw-text-sm tw-font-medium tw-text-white tw-transition-all tw-duration-200 theme-btn-bg tw-p-1.5 tw-rounded-lg tw-ring-1 hover:tw-text-white tw-ring-white/10">
                                <span class="tw-sr-only">Menu</span>
                                <svg aria-hidden="true" class="tw-size-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 6l16 0"/><path d="M4 12l16 0"/><path d="M4 18l16 0"/></svg>
                            </button>
                            <button type="button" class="side-bar-collapse tw-hidden lg:tw-inline-flex tw-items-center tw-justify-center tw-text-sm tw-font-medium tw-text-white tw-transition-all tw-duration-200 theme-btn-bg tw-p-1.5 tw-rounded-lg tw-ring-1 hover:tw-text-white tw-ring-white/10">
                                <span class="tw-sr-only">Collapse</span>
                                <svg aria-hidden="true" class="tw-size-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 4m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z"/><path d="M15 4v16"/><path d="M10 10l-2 2l2 2"/></svg>
                            </button>
                        </div>

                        <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-end tw-gap-3">
                            <button type="button" class="tw-hidden lg:tw-inline-flex tw-transition-all tw-ring-1 tw-ring-white/10 tw-duration-200 theme-btn-bg tw-py-1.5 tw-px-3 tw-rounded-lg tw-items-center tw-justify-center tw-text-sm tw-font-medium tw-text-white hover:tw-text-white tw-font-mono">
                                {{ @format_date('now') }}
                            </button>

                            <details class="tw-dw-dropdown tw-relative tw-inline-block tw-text-left">
                                <summary class="tw-dw-m-1 tw-inline-flex tw-transition-all tw-ring-1 tw-ring-white/10 tw-cursor-pointer tw-duration-200 theme-btn-bg tw-py-1.5 tw-px-3 tw-rounded-lg tw-items-center tw-justify-center tw-text-sm tw-font-medium tw-text-white hover:tw-text-white tw-gap-1">
                                    <span class="tw-hidden md:tw-block">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tw-size-5"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M12 10m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/><path d="M6.168 18.849a4 4 0 0 1 3.832 -2.849h4a4 4 0 0 1 3.834 2.855"/></svg>
                                </summary>
                                <ul class="tw-p-2 tw-w-48 tw-absolute tw-right-0 tw-z-10 tw-mt-2 tw-origin-top-right tw-bg-white tw-rounded-lg tw-shadow-lg tw-ring-1 tw-ring-gray-200 focus:tw-outline-none" role="menu" tabindex="-1">
                                    <div class="tw-px-4 tw-pt-3 tw-pb-1" role="none">
                                        <p class="tw-text-sm">@lang('lang_v1.signed_in_as')</p>
                                        <p class="tw-text-sm tw-font-medium tw-text-gray-900 tw-truncate">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</p>
                                    </div>
                                    <li>
                                        <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('supplier-logout-form').submit();"
                                            class="tw-flex tw-items-center tw-gap-2 tw-px-3 tw-py-2 tw-text-sm tw-font-medium tw-text-gray-600 tw-transition-all tw-duration-200 tw-rounded-lg hover:tw-text-gray-900 hover:tw-bg-gray-100" role="menuitem" tabindex="-1">
                                            <svg aria-hidden="true" class="tw-w-5 tw-h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2"/><path d="M9 12h12l-3 -3"/><path d="M18 15l3 -3"/></svg>
                                            @lang('lang_v1.sign_out')
                                        </a>
                                        <form id="supplier-logout-form" action="{{ route('logout') }}" method="POST" class="tw-hidden">@csrf</form>
                                    </li>
                                </ul>
                            </details>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tw-flex-1 tw-overflow-y-auto tw-h-screen" id="scrollable-container">
                @yield('content')
            </div>
            <div class='scrolltop no-print'>
                <div class='scroll icon'><i class="fas fa-angle-up"></i></div>
            </div>
        </main>

        <div class="overlay tw-hidden"></div>
    </div>

    <script src="{{ asset('js/vendor.js?v=' . ($asset_v ?? 1)) }}"></script>
    <script src="{{ asset('js/lang/en.js?v=' . ($asset_v ?? 1)) }}"></script>
    <script src="{{ asset('js/functions.js?v=' . ($asset_v ?? 1)) }}"></script>
    <script src="{{ asset('js/common.js?v=' . ($asset_v ?? 1)) }}"></script>
    <script src="{{ asset('js/app.js?v=' . ($asset_v ?? 1)) }}"></script>
    @yield('js')
</body>
</html>
