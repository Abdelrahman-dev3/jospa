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
        <style>
            .s-hero-sec {
                border-radius: 0 !important;
            }
            .br-15{
                height: 15vh;
            }
            .br-20{
                height: 20vh;
            }
            @media (max-width: 576px) {
                .s-s-mt-100 {
                    margin-top: 100px;
                }
                .br-15{
                    height: 10vh;
                }
            }
        </style>
</head>
<body>
    @include('components.frontend.progress-bar')

    <div class="position-relative" style="height: 17vh;">
        @include('components.frontend.navbar')
    </div>
    <main>
        @include('components.frontend.learn-about-section')
    </main>

    <div class="position-relative br-20"></div>
    @include('components.frontend.footer')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
