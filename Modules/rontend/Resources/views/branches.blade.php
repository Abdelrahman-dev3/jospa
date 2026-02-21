@php
$slot = '';
@endphp
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

    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Zain:ital,wght@0,200;0,300;0,400;0,700;0,800;0,900;1,300;1,400&display=swap" rel="stylesheet">
    @stack('after-styles')
    <style>
        body {
            font-family: 'Zain', sans-serif;
        }
        .vh-17{
            height: 17vh;
        }
        @media (max-width: 576px) {
            .vh-17{
                height: 8vh;
            }
        }
    </style>
</head>
<body>
    @include('components.frontend.progress-bar')

    <div class="position-relative vh-17">

        @include('components.frontend.navbar')
    </div>

    <main>
        @include('components.frontend.slider')
    </main>

    <div class="position-relative vh-17"></div>
    <!-- Footer -->
    @include('components.frontend.footer')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
