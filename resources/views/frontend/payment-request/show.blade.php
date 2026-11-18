<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>{{ __('payment_requests.public.page_title') }} {{ $paymentRequest->number }} | {{ app_name() }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Tajawal', Arial, sans-serif; background: #f8f6f1; color: #333; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px; }
        .card { background: #fff; border-radius: 18px; box-shadow: 0 6px 28px rgba(0,0,0,.08); padding: 28px 22px; max-width: 460px; width: 100%; }
        .logo { display: block; margin: 0 auto 14px; max-height: 44px; }
        h1 { font-size: 21px; margin: 0 0 18px; text-align: center; }
        .row { display: flex; justify-content: space-between; gap: 12px; padding: 11px 0; border-bottom: 1px solid #eee; font-size: 15px; }
        .row span:first-child { color: #777; flex-shrink: 0; }
        .row span:last-child { text-align: end; font-weight: 700; overflow-wrap: anywhere; white-space: pre-line; }
        .total { font-size: 26px; text-align: center; margin: 22px 0 16px; font-weight: 700; }
        .total small { display: block; font-size: 13px; color: #888; font-weight: 400; }
        .btn { display: block; width: 100%; border: 0; border-radius: 12px; padding: 15px; font-size: 16px; font-family: inherit; font-weight: 700; cursor: pointer; margin-top: 10px; background: #bc9a69; color: #fff; }
        .btn.alt { background: #2f2f2f; }
        .btn[disabled] { opacity: .6; cursor: wait; }
        .msg { text-align: center; padding: 14px; border-radius: 12px; font-size: 15px; line-height: 1.6; margin-top: 18px; }
        .msg.ok { background: #e7f6ec; color: #1d6b39; }
        .msg.warn { background: #fff4e0; color: #8a5a00; }
        .msg.err { background: #fde8e8; color: #9b1c1c; }
        .note { font-size: 12px; color: #888; text-align: center; margin-top: 16px; line-height: 1.6; }
    </style>
</head>
<body>
<div class="card">
    <img class="logo" src="{{ asset('images/JOSPA.webp') }}" alt="{{ app_name() }}">
    <h1>{{ __('payment_requests.public.page_title') }}</h1>

    <div class="row"><span>{{ __('payment_requests.fields.number') }}</span><span dir="ltr">{{ $paymentRequest->number }}</span></div>
    <div class="row"><span>{{ __('payment_requests.fields.customer_name') }}</span><span>{{ $paymentRequest->customer_name }}</span></div>
    <div class="row"><span>{{ __('payment_requests.fields.description') }}</span><span>{{ $paymentRequest->description }}</span></div>

    <div class="total">
        {{ number_format((float) $paymentRequest->amount, 2) }} {{ app()->getLocale() === 'ar' ? 'ر.س' : 'SAR' }}
        <small>{{ __('payment_requests.fields.total') }}</small>
        <small>{{ __('payment_requests.fields.vat_inclusive') }}</small>
    </div>

    @if (session('success'))
        <div class="msg ok">{{ session('success') }}</div>
    @elseif (session('error'))
        <div class="msg err">{{ session('error') }}</div>
    @endif

    @if ($paymentRequest->status === 'paid')
        @unless (session('success'))
            <div class="msg ok">{{ __('payment_requests.public.paid') }}</div>
        @endunless
        @if ($paymentRequest->hasOdooInvoicePdf())
            <a class="btn alt" style="text-align:center;text-decoration:none;margin-top:14px" href="{{ route('payment-request.invoice-pdf', $paymentRequest->token) }}" target="_blank" rel="noopener">{{ __('payment_requests.odoo.download_pdf') }}</a>
        @endif
    @elseif ($paymentRequest->status === 'cancelled')
        <div class="msg warn">{{ __('payment_requests.public.cancelled') }}</div>
    @else
        @php
            // The bank may still be processing the last payment: warn before offering another charge.
            $processing = $lastAttempt
                && $lastAttempt->status === 'pending'
                && $lastAttempt->result_code
                && preg_match('/^(000\.200|800\.400\.5|100\.400\.500)/', $lastAttempt->result_code)
                && ! str_starts_with($lastAttempt->result_code, '000.200.000');
        @endphp
        @if (session('info') || $processing)
            <div class="msg warn">{{ __('payment_requests.public.processing') }}</div>
        @endif

        <form method="POST" action="{{ route('payment-request.pay', $paymentRequest->token) }}" id="pay-form">
            @csrf
            @foreach ($brands as $brand)
                <button class="btn {{ $brand === 'MADA' ? 'alt' : '' }}" type="submit" name="brand" value="{{ $brand }}">
                    {{ $brand === 'MADA' ? __('payment_requests.public.pay_mada') : __('payment_requests.public.pay_card') }}
                </button>
            @endforeach
        </form>
        <p class="note">{{ __('payment_requests.public.secure_note') }}</p>
    @endif

    <p class="note">{{ __('payment_requests.disclaimer') }}</p>
</div>
<script>
    // Prevent double submission; the server is idempotent as well.
    (function () {
        var form = document.getElementById('pay-form');
        if (!form) return;
        form.addEventListener('submit', function (e) {
            var clicked = e.submitter;
            if (clicked && !form.querySelector('input[name="brand"]')) {
                var hidden = document.createElement('input');
                hidden.type = 'hidden'; hidden.name = 'brand'; hidden.value = clicked.value;
                form.appendChild(hidden);
            }
            form.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
        });
    })();
</script>
</body>
</html>
