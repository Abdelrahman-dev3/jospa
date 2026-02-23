<!DOCTYPE html>
<html lang="{{ $htmlLang ?? app()->getLocale() }}" dir="{{ $htmlDir ?? language_direction() }}" class="{{ trim('theme-fs-sm ' . ($htmlClass ?? '')) }}">
<head>
    @yield('head')
</head>
<body
    @if(!empty($bodyClass)) class="{{ $bodyClass }}" @endif
    @if(!empty($bodyDir)) dir="{{ $bodyDir }}" @endif
    @if(!empty($bodyLang)) lang="{{ $bodyLang }}" @endif
>
    @php
        $showProgress = $showProgress ?? false;
        $showNavbar = $showNavbar ?? true;
        $showFooter = $showFooter ?? true;
        $showTopNotifications = $showTopNotifications ?? false;
        $showBottomNotifications = $showBottomNotifications ?? false;
        $topSpacerHeight = $topSpacerHeight ?? '71.4px';
        $bottomSpacerHeight = $bottomSpacerHeight ?? null;
    @endphp

    @if($showProgress)
        @include('components.frontend.progress-bar')
    @endif

    @if($showNavbar)
        <div class="position-relative" @if(!is_null($topSpacerHeight)) style="height: {{ $topSpacerHeight }};" @endif>
            @include('components.frontend.navbar')
            @if($showTopNotifications)
                @include('components.frontend.notifications')
            @endif
        </div>
    @elseif($showTopNotifications)
        @include('components.frontend.notifications')
    @endif

    @yield('content')

    @if(!is_null($bottomSpacerHeight))
        <div class="position-relative" style="height: {{ $bottomSpacerHeight }};"></div>
    @endif

    @if($showBottomNotifications)
        @include('components.frontend.notifications')
    @endif

    @if($showFooter)
        @include('components.frontend.footer')
    @endif

    @yield('scripts')
</body>
</html>
