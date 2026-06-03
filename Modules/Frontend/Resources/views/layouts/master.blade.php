<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ language_direction() }}" class="theme-fs-sm">
<head>
    <meta charset="utf-8">

    <!-- Viewport واحد فقط -->
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title') | {{ app_name() }}</title>

    <link rel="stylesheet" href="{{ mix('css/libs.min.css') }}">
    <link rel="stylesheet" href="{{ mix('css/backend.css') }}">

    @if (language_direction() == 'rtl')
        <link rel="stylesheet" href="{{ asset('css/rtl.css') }}">
    @endif

    <!-- Frontend CSS -->
    <link rel="stylesheet" href="{{ asset('custom-css/frontend.css') }}">

    @stack('after-styles')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body class="{{ auth()->user()->user_setting['theme_scheme'] ?? '' }}">

    <x-frontend.progress-bar />

    <!-- HEADER -->
    <header class="shadow">
        <x-frontend.navbar />
        @unless(View::hasSection('hide-screen-hero'))
            <x-frontend.slider-hero />
        @endunless
    </header>

    <!-- MAIN -->
    <main>
        @yield('content')
    </main>

    <x-frontend.footer />

    <script src="{{ mix('js/backend.js') }}"></script>
    <script src="{{ mix('js/app.js') }}"></script>

    @stack('after-scripts')
</body>
</html>
