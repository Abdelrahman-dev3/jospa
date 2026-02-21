<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ language_direction() }}" class="theme-fs-sm">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title') | {{ app_name() }} — Affiliate</title>

    {{-- SAME CSS AS ADMIN --}}
    <link rel="stylesheet" href="{{ mix('css/icon.min.css') }}">
    <link rel="stylesheet" href="{{ mix('css/libs.min.css') }}">
    <link rel="stylesheet" href="{{ mix('css/backend.css') }}">
    <link rel="stylesheet" href="{{ asset('css/dark.css') }}">
    <link rel="stylesheet" href="{{ asset('custom-css/dashboard.css') }}">

    @if (language_direction() == 'rtl')
        <link rel="stylesheet" href="{{ asset('css/rtl.css') }}">
    @endif

    <link rel="stylesheet" href="{{ asset('css/customizer.css') }}">
</head>

<body class="{{ auth()->user()->user_setting['theme_scheme'] ?? '' }}">
    {{-- Loader (نفس الأدمن) --}}
    <div id="loading">
        <x-partials._body_loader />
    </div>

    {{-- ===================== --}}
    {{--     Affiliate Sidebar --}}
    {{-- ===================== --}}
    <div class="sidebar-base sidebar left-bordered sidebar-color">
        <div class="d-flex align-items-center justify-content-start">
            <div class="logo-main">
                <a href="{{ route('affiliate.dashboard') }}" class="navbar-brand">
                    <img class="logo-normal img-fluid" src="{{ asset('images/JOSPA.webp') }}" height="30">
                </a>
            </div>
            <div class="sidebar-toggle" data-toggle="sidebar" data-active="true">
                <i class="icon">
                    <svg class="icon-20" width="20" viewBox="0 0 24 24">
                        <path d="M4.25 12.2744L19.25 12.2744" stroke="currentColor" stroke-width="1.5"
                            stroke-linecap="round"></path>
                        <path d="M10.2998 18.2988L4.2498 12.2748L10.2998 6.24976" stroke="currentColor"
                            stroke-width="1.5" stroke-linecap="round"></path>
                    </svg>
                </i>
            </div>
        </div>

        <div class="sidebar-body pt-0 data-scrollbar">
            <div class="sidebar-list">
                <ul class="navbar-nav iq-main-menu">

                    <li class="nav-item {{ request()->routeIs('affiliate.dashboard') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('affiliate.dashboard') }}">
                            <i class="ri-dashboard-line"></i>
                            <span class="item-name">لوحة التحكم</span>
                        </a>
                    </li>

                    <li class="nav-item {{ request()->routeIs('affiliate.links') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('affiliate.links') }}">
                            <i class="ri-links-line"></i>
                            <span class="item-name">روابط المسوّق</span>
                        </a>
                    </li>

                    <li class="nav-item {{ request()->routeIs('affiliate.stats') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('affiliate.stats') }}">
                            <i class="ri-bar-chart-line"></i>
                            <span class="item-name">الإحصائيات</span>
                        </a>
                    </li>

                    <li class="nav-item {{ request()->routeIs('affiliate.conversions') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('affiliate.conversions') }}">
                            <i class="ri-exchange-dollar-line"></i>
                            <span class="item-name">التحويلات</span>
                        </a>
                    </li>

                    <li class="nav-item {{ request()->routeIs('affiliate.earnings') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('affiliate.earnings') }}">
                            <i class="ri-money-dollar-circle-line"></i>
                            <span class="item-name">الأرباح</span>
                        </a>
                    </li>

                    <li class="nav-item {{ request()->routeIs('affiliate.withdraw') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('affiliate.withdraw') }}">
                            <i class="ri-bank-card-line"></i>
                            <span class="item-name">طلبات السحب</span>
                        </a>
                    </li>

                </ul>
            </div>
        </div>
    </div>

     <div class="main-content wrapper">
        <div
            class="position-relative pr-hide @hasPermission('menu_builder_sidebar')
                    {{ !isset($isBanner) ? 'iq-banner' : '' }} default
                    @endhasPermission">
            <!-- Header -->
            @include('backend.includes.header')
            <!-- /Header -->
            @if (!isset($isBanner))
                <!-- Header Banner Start-->
                @hasPermission('menu_builder_sidebar')
                    @include('components.partials.sub-header')
                @endhasPermission
                <!-- Header Banner End-->
            @endif
        </div>

    {{-- ===================== --}}
    {{--     MAIN CONTENT      --}}
    {{-- ===================== --}}
    <div class="main-content wrapper">
        <div class="content-inner p-4">
            @yield('content')
        </div>
    </div>

    {{-- ===== FOOTER (نفس الأدمن بس بدون قوائم) ===== --}}
    <div class="footer">
        @include('backend.includes.footer')
    </div>

    {{-- JS --}}
    <script src="{{ mix('js/backend.js') }}"></script>
    <script src="{{ asset('js/iqonic-script/utility.js') }}"></script>
    <script src="{{ asset('js/app.js') }}"></script>

    @stack('scripts')
</body>

</html>
