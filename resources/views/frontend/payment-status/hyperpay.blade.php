<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ language_direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ app_name() }}</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f7f4ee;
            color: #1f1f1f;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .payment-card {
            width: 100%;
            max-width: 640px;
            background: #fff;
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 18px 50px rgba(0, 0, 0, 0.08);
        }
        .payment-card h1 {
            margin: 0 0 12px;
            font-size: 26px;
        }
        .payment-card p {
            margin: 0 0 20px;
            line-height: 1.7;
            color: #666;
        }
        .payment-card .brands {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }
        .payment-card .brand {
            border: 1px solid #ececec;
            border-radius: 999px;
            padding: 8px 14px;
            background: #fafafa;
            font-size: 14px;
            font-weight: 600;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="payment-card">
        <h1>{{ app()->getLocale() === 'ar' ? 'الدفع عبر Hyperpay' : 'Pay with Hyperpay' }}</h1>
        <p>{{ app()->getLocale() === 'ar' ? 'أكمل بيانات البطاقة لإتمام عملية الدفع بشكل آمن.' : 'Complete your card details to finish the payment securely.' }}</p>
        <div class="brands">
            @foreach($brandLabels as $brand)
                <span class="brand">{{ $brand }}</span>
            @endforeach
        </div>
        <div id="applePayUnsupported" style="display: none; background: #fff3cd; color: #856404; border: 1px solid #ffeeba; border-radius: 12px; padding: 16px; margin-bottom: 18px; font-size: 15px; line-height: 1.6;">
            {{ app()->getLocale() === 'ar' ? 'عذرًا، Apple Pay غير مدعوم على هذا الجهاز أو المتصفح. يرجى استخدام متصفح Safari على جهاز Apple مدعوم.' : 'Apple Pay is not supported on this device/browser. Please use Safari on a supported Apple device.' }}
        </div>
        <form action="{{ $resultUrl }}" class="paymentWidgets" data-brands="{{ $brands }}"></form>
    </div>

    <script>
        if ("{{ $brands }}" === "APPLEPAY" && !(window.ApplePaySession && window.ApplePaySession.canMakePayments())) {
            var warningEl = document.getElementById('applePayUnsupported');
            if (warningEl) {
                warningEl.style.display = 'block';
            }
        }
    </script>

    {{-- wpwlOptions MUST be declared before the widget script --}}
    <script>
        var wpwlOptions = {
            locale: "{{ app()->getLocale() === 'ar' ? 'ar' : 'en' }}",
            paymentTarget: "_top",
            applePay: {
                displayName: "{{ config('app.name', 'Jospa') }}",
                total: { label: "{{ config('app.name', 'Jospa') }}" },
                countryCode: "SA",
                currencyCode: "SAR",
                style: "black",
                supportedNetworks: ["visa", "masterCard", "mada"],
                merchantCapabilities: ["supports3DS"]
            },
            onReady: function() {
                console.log('Hyperpay widget ready');
            },
            onError: function(error) {
                console.error('Hyperpay widget error:', error);
                var message = error.message || 'Unknown error';
                if (message.indexOf('400') !== -1 || message.indexOf('Apple Pay session') !== -1) {
                    message = "{{ app()->getLocale() === 'ar' ? 'فشل بدء جلسة Apple Pay (خطأ 400). يرجى التأكد من تفعيل Apple Pay على القناة وربط النطاق وبطاقة التاجر في حساب Hyperpay.' : 'Failed to start Apple Pay session (status 400). Please verify Apple Pay channel activation and domain/merchant binding on Hyperpay.' }}";
                }
                alert(message);
            },
            beforeSubmit: function() {
                console.log('Payment before submit');
                return true;
            },
            afterSubmit: function() {
                console.log('Payment after submit');
            },
            onPaymentBrandChanged: function(brand) {
                console.log('Payment brand changed:', brand);
            }
        };
    </script>
    {{-- Widget script MUST load AFTER the .paymentWidgets form is in the DOM --}}
    <script src="{{ $widgetScriptUrl }}"></script>
</body>
</html>