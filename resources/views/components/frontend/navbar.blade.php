<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Zain:ital,wght@0,200;0,300;0,400;0,700;0,800;0,900;1,300;1,400&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('pages-css/second-navbar.css') }}">
<style>
    @media (max-width: 480px) {
        .more-btn-nav {
            height: 40px;
        }
    }
    @media (max-width: 768px) {
        .settings {
            min-width: fit-content !important;
            margin-bottom: 15px;
        }
    }
    @media (max-width: 992px) {
        .loyalty {
            width: fit-content !important;
            margin: 0;
        }
        .logo-img {
            width: 148px !important;
        }
        .mob-nav {
            display: flex !important;
            align-items: center;
            justify-content: space-around;
        }
    }
</style>
    <div class="m-nav d-none d-lg-flex flex-column">
        <div class="top-bar d-flex align-items-center gap-3 px-3 py-1" style="background: transparent;width: 100%;justify-content: space-between;padding: 5px 34px !important;">
            <div class="other d-flex" style="gap: 35px;">
                <!-- Email -->
                <a href="mailto:info@jospa-sa.com" class="contact-info">
                    <span class="iconify" data-icon="mdi:email" data-width="18" data-height="18"></span> info@jospa-sa.com
                </a>
            
                <!-- Phone -->
                <a href="tel:966920012924" class="contact-info">
                    <span class="iconify" data-icon="mdi:phone" data-width="18" data-height="18"></span> 966920012924+
                </a>
            </div>
            <div class="social d-flex" style="gap: 35px;">
                <!-- WhatsApp -->
                <a href="https://wa.me/966920012924" target="_blank" class="social-icon">
                    <span class="iconify" data-icon="mdi:whatsapp" data-width="20" data-height="20"></span>
                </a>
                <!-- Instagram -->
                <a href="https://www.instagram.com/jospa_sa/#" target="_blank" class="social-icon">
                    <span class="iconify" data-icon="mdi:instagram" data-width="20" data-height="20"></span>
                </a>
                <!-- X (Twitter) -->
                <a href="https://x.com/Jospa_sa" target="_blank" class="social-icon">
                    <span class="iconify" data-icon="mdi:twitter" data-width="20" data-height="20"></span>
                </a>
            </div>
        </div>
        <div style="display: flex;width: 100%;">
            <div class="logo"><a href="/"> <img src="{{asset('images/jospalogo.png')}}"></a></div>
            <div class="links">
                <ul class="navbar-nav mb-2 mb-lg-0 d-flex align-items-center gap-4" style="flex-direction: row;white-space: nowrap;z-index: 999999;">
    
                        <li class="nav-item h5">
                            <a class="nav-link text-white {{ request()->routeIs('frontend.home') ? 'text-active' : '' }}"
                               href="{{ route('frontend.home') }}">
                                {{ __('messagess.nav_home') }}
                            </a>
                        </li>
    
                        <li class="nav-item h5">
                            <a class="nav-link text-white {{ request()->routeIs('frontend.about') ? 'text-active' : '' }}"
                               href="{{ route('frontend.about') }}">
                                {{ __('messagess.nav_about') }}
                            </a>
                        </li>
    
                        <li class="nav-item h5">
                            <a class="nav-link text-white {{ request()->routeIs('frontend.services') ? 'text-active' : '' }}"
                               href="{{ route('frontend.services') }}">
                                {{ __('messagess.nav_services') }}
                            </a>
                        </li>
    
                        <li class="nav-item h5">
                            <a class="nav-link text-white {{ request()->routeIs('frontend.Packages') ? 'text-active' : '' }}"
                               href="{{ route('frontend.Packages') }}">
                                {{ __('messagess.nav_package') }}
                            </a>
                        </li>
    
                        <li class="nav-item h5">
                            <a class="nav-link text-white {{ request()->routeIs('frontend.Ouroffers') ? 'text-active' : '' }}"
                               href="{{ route('frontend.Ouroffers') }}">
                                {{ __('messagess.our_offers') }}
                            </a>
                        </li>
    
                        <li class="nav-item h5">
                            <a class="nav-link text-white {{ request()->routeIs('frontend.Shop') ? 'text-active' : '' }}"
                               href="{{ route('frontend.Shop') }}">
                                {{ __('messagess.store') }}
                            </a>
                        </li>
    
                        <li class="nav-item h5">
                            <a class="nav-link text-white {{ request()->routeIs('gift.page') ? 'text-active' : '' }}"
                               href="{{ route('gift.page') }}">
                               {{ __('messagess.gift_cards') }}
                            </a>
                        </li>
    
                        <li class="nav-item h5">
                            <a class="nav-link text-white {{ request()->routeIs('frontend.branches') ? 'text-active' : '' }}"
                               href="{{ route('frontend.branches') }}">
                                {{ __('messagess.our_branches') }}
                            </a>
                        </li>
    
                        <li class="nav-item h5">
                            <a class="nav-link text-white {{ request()->routeIs('frontend.contact') ? 'text-active' : '' }}"
                               href="{{ route('frontend.contact') }}">
                                {{ __('messagess.nav_contact') }}
                            </a>
                        </li>
                    </ul>
            </div>
            <div class="settings d-flex justify-content-center align-items-center gap-4">
            <!-- Language -->
            <div class="language-selector text-center">
                <div class="icon-circle" style="cursor:pointer">
                    <span class="iconify" data-icon="mdi:earth"></span>
                </div>
                <div class="icon-text">{{ __('messagess.lang') }}</div>
    
                <div class="dropdown-content" style="top:55px;left:auto;right:0">
                    <a href="{{ route('language.switch', 'ar') }}">
                        العربية
                    </a>
                    <a href="{{ route('language.switch', 'en') }}">
                        English
                    </a>
                </div>
            </div>
    
            <!-- Cart -->
            <a href="{{ route('cart.page') }}">
            <div class="text-center">
                <div class="icon-circle">
                    <span class="iconify" data-icon="mdi-light:cart"></span>
                </div>
                <div class="icon-text">{{ __('messagess.nav_cart') }}</div>
            </div>
            </a>
            <!-- My Profile -->
            <a href="{{ route('profile') }}">
            <div class="text-center">
                <div class="icon-circle">
                    <span class="iconify" data-icon="fluent:person-28-regular"></span>
                </div>
                @if (Auth::check())
                <div class="icon-text">{{ __('messagess.profile') }}</div>
                @else
                <div class="icon-text">{{ __('auth.signin') }}</div>
                @endif
            </div>
            </a>
    
        </div>
            <div class="loyalty" style="width: 15% !important;height: 100%;display: flex;justify-content: left;align-items: center;">
                <a href="{{route('home.loyalety')}}" class="more-btn-nav">
                    <p style="color: #BF9456;font-size: 16px;margin: 0 13px;font-weight: bold;"> <img style="width: 22px;margin: 0 7px;" src="{{ asset('images/icons/basil-present-outline-11.svg') }}" > {{ __('messagess.loyalty_points') }}</p>
                </a>
            </div>
        </div>
    </div>

    <!-- زرار فتح المينيو -->
    <div class="mob-nav">
        <button class="btn mob-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-controls="mobileMenu">
            <span class="iconify" data-icon="hugeicons:menu-02" data-width="30" data-height="30"  style="color: black;"></span>
        </button>
        
        <a href="/" style="display: flex;align-items: center;"> <img class="logo-img" style="width: 118px;" src="{{asset('images/jospalogo.png')}}"></a>
        
        <div class="settings d-flex justify-content-center align-items-center gap-4" style="margin-top: 22px !important;">
            <!-- My Profile -->
            <a href="{{ route('profile') }}">
            <div class="text-center">
                <div class="icon-circle">
                    <span class="iconify" data-icon="fluent:person-28-regular"></span>
                </div>
                <div class="icon-text">{{ __('messagess.profile') }}</div>
            </div>
            </a> 
            <!-- Cart -->
            <a href="{{ route('cart.page') }}">
            <div class="text-center">
                <div class="icon-circle">
                    <span class="iconify" data-icon="mdi-light:cart"></span>
                </div>
                <div class="icon-text">{{ __('messages.cart') }}</div>
            </div>
            </a>
        </div>

    </div>

    <!-- المينيو الجانبي -->
    <div class="offcanvas offcanvas-start bg-white " tabindex="-1" id="mobileMenu" aria-labelledby="mobileMenuLabel">
      <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="mobileMenuLabel">{{ __('menu.list') }}</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body">
        <ul class="navbar-nav">
          <li class="nav-item">
            <a class="nav-link  {{ request()->routeIs('frontend.home') ? 'text-active' : '' }}" href="{{ route('frontend.home') }}">
              {{ __('messagess.nav_home') }}
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link  {{ request()->routeIs('frontend.about') ? 'text-active' : '' }}" href="{{ route('frontend.about') }}">
              {{ __('messagess.nav_about') }}
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link  {{ request()->routeIs('frontend.services') ? 'text-active' : '' }}" href="{{ route('frontend.services') }}">
              {{ __('messagess.nav_services') }}
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link  {{ request()->routeIs('frontend.Packages') ? 'text-active' : '' }}"href="{{ route('frontend.Packages') }}">
                {{ __('messagess.nav_package') }}
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link  {{ request()->routeIs('frontend.Ouroffers') ? 'text-active' : '' }}"href="{{ route('frontend.Ouroffers') }}">
                {{ __('messagess.our_offers') }}
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link  {{ request()->routeIs('frontend.Shop') ? 'text-active' : '' }}"href="{{ route('frontend.Shop') }}">
                {{ __('messagess.store') }}
            </a>
          </li>
         <li class="nav-item">
            <a class="nav-link  {{ request()->routeIs('gift.page') ? 'text-active' : '' }}"href="{{ route('gift.page') }}">
               {{ __('messagess.gift_cards') }}
            </a>
          </li>
         <li class="nav-item">
            <a class="nav-link  {{ request()->routeIs('frontend.branches') ? 'text-active' : '' }}"href="{{ route('frontend.branches') }}">
                {{ __('messagess.our_branches') }}
            </a>
         </li>

         <li class="nav-item">
            <a class="nav-link  {{ request()->routeIs('frontend.contact') ? 'text-active' : '' }}"href="{{ route('frontend.contact') }}">
                {{ __('messagess.nav_contact') }}
            </a>
         </li>
         <li  class="nav-item">
            <a href="{{ route('language.switch', 'en') }}" style="color:#cf9233;text-decoration-line: none;">English</a> |
            <a href="{{ route('language.switch', 'ar') }}" style="color:#cf9233;text-decoration-line: none;">العربية</a>
         </li>
        <div class="loyalty" style="width: 100% !important;height: 100%;margin: 11px;display: flex;justify-content: center;align-items: center;">
            <a href="{{route('home.loyalety')}}" class="more-btn-nav" style="width: 55%;">
                <p style="color: #BF9456;font-size: 16px;margin: 0 13px;font-weight: bold;"> <img style="width: 22px;margin: 0 7px;" src="{{ asset('images/icons/basil-present-outline-11.svg') }}" > {{ __('messagess.loyalty_points') }}</p>
            </a>
        </div>
        </ul>
      </div>
    </div>
    <script src="https://code.iconify.design/3/3.1.0/iconify.min.js"></script>

