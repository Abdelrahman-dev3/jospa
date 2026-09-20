@once
    @if ((int) setting('saudi_national_day_theme', 0) === 1)
        <link rel="stylesheet" href="{{ asset('custom-css/saudi-national-day.css') }}?v={{ filemtime(public_path('custom-css/saudi-national-day.css')) }}">
    @endif
@endonce
