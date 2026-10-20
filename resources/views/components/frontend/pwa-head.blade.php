<link rel="manifest" href="{{ url('/manifest.webmanifest') }}">
<meta name="theme-color" content="#bf9456">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Jospa">
<link rel="apple-touch-icon" href="{{ url('/images/pwa/icon-192.png') }}">
<link rel="stylesheet" href="{{ asset('custom-css/pwa-install.css') }}">
<script src="{{ url('/js/pwa-install.js') }}" data-worker="{{ url('/sw.js') }}" defer></script>
