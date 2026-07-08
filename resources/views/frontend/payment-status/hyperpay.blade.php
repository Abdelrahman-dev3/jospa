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
    <script>
        var wpwlOptions = {
            locale: "{{ app()->getLocale() === 'ar' ? 'ar' : 'en' }}",
            paymentTarget: "_top"
        };
    </script>
    <script src="{{ $widgetScriptUrl }}"></script>
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
        <form action="{{ $resultUrl }}" class="paymentWidgets" data-brands="{{ $brands }}"></form>
    </div>
</body>
</html>
