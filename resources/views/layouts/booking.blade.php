<!DOCTYPE html>
<html dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}" class="{{ app()->getLocale() }}">
<head>
    @yield('head')
</head>
<body dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}" class="{{ app()->getLocale() }}">
    @php
        $showTopNotifications = $showTopNotifications ?? true;
        $showBottomNotifications = $showBottomNotifications ?? false;
        $showTopSpacer = $showTopSpacer ?? true;
    @endphp

    <div class="position-relative" @if($showTopSpacer) style="height: 15vh;" @endif>
        @include('components.frontend.navbar')
        @if($showTopNotifications)
            @include('components.frontend.notifications')
        @endif
    </div>

    @yield('content')

    @if($showBottomNotifications)
        @include('components.frontend.notifications')
    @endif

    @include('components.frontend.footer')

    @yield('scripts')
</body>
</html>
