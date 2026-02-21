@hasPermission('menu_builder_sidebar')
<div class="sidebar-base pr-hide
            {{ getCustomizationSetting('sidebar_show') == 'sidebar-none' ? 'sidebar-none' : 'sidebar' }}
            {{ !empty(getCustomizationSetting('sidebar_menu_style')) ? getCustomizationSetting('sidebar_menu_style') : 'left-bordered' }}
            {{ getCustomizationSetting('sidebar_color') }}
            {{ !empty(getCustomizationSetting('sidebar_type')) ? implode(' ',getCustomizationSetting('sidebar_type')) : '' }}
            "
            data-toggle="main-sidebar" id="sidebar" data-sidebar="responsive">
    <div class="d-flex align-items-center justify-content-start">
        <div class="logo-main">
            <a href="{{route('backend.dashboard')}}" class="navbar-brand" >
                <img class="logo-normal img-fluid" src="{{ asset('images/JOSPA.webp') }}" height="30" alt="{{ app_name() }}">
                <img class="logo-normal dark-normal img-fluid" src="{{ asset('images/JOSPA.webp') }}" height="30" alt="{{ app_name() }}">
                <img class="logo-mini img-fluid" src="{{ asset('images/JOSPA.webp') }}" height="30" alt="{{ app_name() }}">
                <img class="logo-mini dark-mini img-fluid" src="{{ asset('images/JOSPA.webp') }}" height="30" alt="{{ app_name() }}">
            </a>
        </div>
        <div class="sidebar-toggle" data-toggle="sidebar" data-active="true">
            <i class="icon">
                <svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4.25 12.2744L19.25 12.2744" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                    <path d="M10.2998 18.2988L4.2498 12.2748L10.2998 6.24976" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </i>
        </div>
    </div>
    <div class="sidebar-body pt-0 data-scrollbar">
        <div class="sidebar-list" id="sidebar">
            <ul class="navbar-nav iq-main-menu" id="sidebar-menu">
@php
    $menu = new \App\Http\Middleware\GenerateMenus();
    $menu = $menu->handle('menu', 'vertical', 'ARRAY_MENU');
    
    $filteredItems = $menu->roots()->filter(function($item) {
        $hiddenItems = [
            'staff_earnings', 'أرباح', 'earnings',
            'staffs_payouts', 'مدفوعات', 'payouts',
            'reviews', 'التقييمات', 'review'
        ];

        foreach ($hiddenItems as $hidden) {
            if (str_contains(strtolower($item->title), strtolower($hidden))) {
                return false;
            }
        }
        return true;
    });
@endphp

@include(config('laravel-menu.views.bootstrap-items'), ['items' => $filteredItems])


                @hasPermission('view_gift')
                <li class="nav-item {{ request()->routeIs('app.gift') ? 'active' : '' }}">
                    <a href="{{ route('app.gift') }}" class="nav-link {{ request()->routeIs('app.gift') ? 'active' : '' }}">
                        <i class="fa fa-chart-bar"></i>
                        <span class="item-name">{{ __('messagess.gifts') }}</span>
                    </a>
                </li>
                @endhasPermission

                @hasPermission('view_loyalty')
                <li class="nav-item {{ request()->routeIs('app.loyalty') ? 'active' : '' }}">
                    <a href="{{ route('app.loyalty') }}" class="nav-link {{ request()->routeIs('app.loyalty') ? 'active' : '' }}">
                        <i class="fas fa-coins"></i>
                        <span class="item-name">{{ __('messages.loyalty_management') }}</span>
                    </a>
                </li>
                @endhasPermission

                @hasPermission('view_offers')
                <li class="nav-item {{ request()->routeIs('app.offers') ? 'active' : '' }}">
                    <a href="{{ route('app.offers') }}" class="nav-link {{ request()->routeIs('app.offers') ? 'active' : '' }}">
                        <i class="fa fa-tags"></i>
                        <span class="item-name">{{ __('messagess.our_offers') }}</span>
                    </a>
                </li>
                @endhasPermission

                @hasPermission('view_ads')
                <li class="nav-item {{ request()->routeIs('app.ads') ? 'active' : '' }}">
                    <a href="{{ route('app.ads') }}" class="nav-link {{ request()->routeIs('app.ads') ? 'active' : '' }}">
                        <i class="fa-solid fa-ad"></i>
                        <span class="item-name">{{ __('messagess.Ads') }}</span>
                    </a>
                </li>
                @endhasPermission

                @hasPermission('view_vartext')
                <li class="nav-item {{ request()->routeIs('app.text') ? 'active' : '' }}">
                    <a href="{{ route('app.text') }}" class="nav-link {{ request()->routeIs('app.text') ? 'active' : '' }}">
                        <i class="fa-solid fa-pen-to-square"></i>
                        <span class="item-name">{{ __('messagess.mainwebtext') }}</span>
                    </a>
                </li>
                @endhasPermission

                @hasPermission('view_reject_reasons')
                <li class="nav-item {{ request()->routeIs('app.reject') ? 'active' : '' }}">
                    <a href="{{ route('app.reject') }}" class="nav-link {{ request()->routeIs('app.reject') ? 'active' : '' }}">
                        <i class="fa-solid fa-ban"></i>
                        <span class="item-name">{{ __('messagess.cancellation_of_reservation') }}</span>
                    </a>
                </li>
                @endhasPermission

                @hasPermission('view_terms_and_conditions')
                <!--<li class="nav-item {{ request()->routeIs('app.TermsAndConditions') ? 'active' : '' }}">-->
                <!--    <a href="{{ route('app.TermsAndConditions') }}" class="nav-link {{ request()->routeIs('app.TermsAndConditions') ? 'active' : '' }}">-->
                <!--        <i class="fas fa-file-contract"></i>-->
                <!--        <span class="item-name">{{ __('messages.TermsAndConditions') }}</span>-->
                <!--    </a>-->
                <!--</li>-->
                @endhasPermission

                @hasPermission('view_sms')
                <!--<li class="nav-item {{ request()->routeIs('app.sms') ? 'active' : '' }}">-->
                <!--    <a href="{{ route('app.sms') }}" class="nav-link {{ request()->routeIs('app.sms') ? 'active' : '' }}">-->
                <!--        <i class="fas fa-sms"></i>-->
                <!--        <span class="item-name">{{ __('messages.sms') }}</span>-->
                <!--    </a>-->
                <!--</li>-->
                @endhasPermission

            </ul>
        </div>
    </div>
    <div class="sidebar-footer"></div>
</div>
@endhasPermission
