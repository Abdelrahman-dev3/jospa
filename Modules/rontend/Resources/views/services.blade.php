<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ language_direction() }}" class="theme-fs-sm">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">

    <title>@yield('title') | {{ app_name() }}</title>

    <link rel="stylesheet" href="{{ mix('css/libs.min.css') }}">
    <link rel="stylesheet" href="{{ mix('css/backend.css') }}">
    @if (language_direction() == 'rtl')
        <link rel="stylesheet" href="{{ asset('css/rtl.css') }}">
    @endif
    <link rel="stylesheet" href="{{ asset('custom-css/frontend.css') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    @stack('after-styles')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
        rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css"/>
        <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css"/>


    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <style>
        .swiper-pagination-bullet-active {
            background-color: #BF9456;
            opacity: 1;
        }
        .swiper-slide{
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .swiper-slide img{
            width: 65%;
            height: 250px;
            object-fit: cover;
            border-radius: 8px;
        }
        @media (max-width: 768px) {
            .swiper-slide img{
                width: 100%;
                object-fit: contain;
            }

        }
        
    </style>
</head>
<body>
    @include('components.frontend.progress-bar')

    <div class="position-relative" style="height: 17vh;">

        @include('components.frontend.navbar')
    </div>
    <!-- Swiper -->
    <div class="swiper mySwiper" style="display: flex; justify-content: center; align-items: center; margin-top: 37px;">
        <div class="swiper-wrapper">
            @foreach($ads as $ad)
                <div class="swiper-slide">
                    <img src="{{ asset($ad->image) }}">
                </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="swiper-pagination"></div>
    </div>

    <!-- Page Content -->
    <main>
        @include('components.frontend.services-section', compact('categories', 'services', 'packages'))
    </main>

    <div class="position-relative" style="height: 17vh;">
    </div>
    <!-- Footer -->
    @include('components.frontend.footer')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
    <script>
        var swiper = new Swiper(".mySwiper", {
            loop: true,
            autoplay: {
                delay: 3000,
                disableOnInteraction: false,
            },
            effect: 'fade', // تأثير fade بسيط
            fadeEffect: {
                crossFade: true
            },
            pagination: {
                el: ".swiper-pagination",
                clickable: true, // النقط قابلة للنقر للتنقل
            },
        });
    </script>
</body>
</html>
