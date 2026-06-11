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
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .redirect-card {
            width: 100%;
            max-width: 520px;
            background: #fff;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 18px 50px rgba(0, 0, 0, 0.08);
            text-align: center;
        }
        .redirect-card h1 {
            margin: 0 0 12px;
            font-size: 24px;
        }
        .redirect-card p {
            margin: 0 0 18px;
            line-height: 1.7;
            color: #585858;
        }
        .redirect-card button {
            border: 0;
            background: #bf9456;
            color: #fff;
            border-radius: 10px;
            padding: 12px 18px;
            font-size: 15px;
            cursor: pointer;
        }
    </style>
</head>
<body onload="document.getElementById('urpayHostedForm').submit()">
    <div class="redirect-card">
        <h1>{{ app()->getLocale() === 'ar' ? 'تحويل إلى بوابة الدفع' : 'Redirecting to Payment Gateway' }}</h1>
        <p>{{ $message ?? '' }}</p>

        <form id="urpayHostedForm" action="{{ $actionUrl }}" method="{{ strtoupper($method ?? 'POST') === 'GET' ? 'GET' : 'POST' }}">
            @foreach(($fields ?? []) as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <noscript>
                <button type="submit">{{ app()->getLocale() === 'ar' ? 'متابعة الدفع' : 'Continue to Payment' }}</button>
            </noscript>
        </form>
    </div>
</body>
</html>
