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
<link href="https://fonts.googleapis.com/css2?family=Zain:ital,wght@0,200;0,300;0,400;0,700;0,800;0,900;1,300;1,400&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>
</head>
<body>
    @include('components.frontend.progress-bar')
    @include('components.frontend.notifications')

    <div class="position-relative" style="height: 21vh;">
        @include('components.frontend.navbar')
    </div>
    <x-frontend.payment
        :items-count="$serviceCount + $productCount + $GifttCount"
        :total-price="$finalPrice"
        page-name="cart"
        :products-amount="$productPrice"
        :wallet="$wallet->amount ?? 0"
        :loyalty-balance="$loyaltyBalance ?? 0"
        :branches="$branches"  
        :tapPaymentSources="$tapPaymentSources"
        :defaultPaymentMethod="$defaultPaymentMethod"  
        :defaultPaymentSource="$defaultPaymentSource"
        :paymentMethods="$paymentMethods"  
    />
    <div class="position-relative" style="height: 19vh;"></div>
    <!-- Footer -->
    @include('components.frontend.footer')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    @if(session('error'))
        <script>
            createNotify({
                title: 'خطأ',
                desc: @json(session('error')),
                autoplay: true
            });
        </script>
        @endif

        @if(session('success'))
        <script>
            createNotify({
                title: 'نجاح',
                desc: @json(session('success')),
                autoplay: true
            });
        </script>
    @endif

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
</body>
</html>
