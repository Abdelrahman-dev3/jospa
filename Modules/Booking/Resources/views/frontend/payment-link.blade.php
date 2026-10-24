<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('booking.payment_link_page_title') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', Arial, sans-serif; background: #f8f6f1; color: #333; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px; box-sizing: border-box; }
        .card { background: #fff; border-radius: 18px; box-shadow: 0 6px 28px rgba(0,0,0,.08); padding: 32px 28px; max-width: 440px; width: 100%; }
        h1 { font-size: 22px; margin: 0 0 18px; text-align: center; }
        .row { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid #eee; font-size: 15px; }
        .row span:first-child { color: #777; }
        .row span:last-child { text-align: end; font-weight: 700; }
        .total { font-size: 22px; text-align: center; margin: 22px 0 18px; font-weight: 700; }
        .btn { display: block; width: 100%; border: 0; border-radius: 12px; padding: 14px; font-size: 16px; font-family: inherit; font-weight: 700; cursor: pointer; margin-top: 10px; background: #bc9a69; color: #fff; }
        .btn.alt { background: #2f2f2f; }
        .msg { text-align: center; padding: 14px; border-radius: 12px; font-size: 16px; }
        .msg.ok { background: #e7f6ec; color: #1d6b39; }
        .msg.warn { background: #fff4e0; color: #8a5a00; }
        .msg.err { background: #fde8e8; color: #9b1c1c; margin-bottom: 14px; }
    </style>
</head>
<body>
<div class="card">
    <h1>{{ __('booking.payment_link_page_title') }} #{{ $booking->id }}</h1>

    @if (session('error'))
        <div class="msg err">{{ session('error') }}</div>
    @endif

    @if ($customerName !== '')
        <div class="row"><span>{{ __('booking.payment_link_customer') }}</span><span>{{ $customerName }}</span></div>
    @endif
    @if ($serviceNames !== '')
        <div class="row"><span>{{ __('booking.payment_link_services') }}</span><span>{{ $serviceNames }}</span></div>
    @endif
    @if ($branchName)
        <div class="row"><span>{{ __('booking.payment_link_branch') }}</span><span>{{ $branchName }}</span></div>
    @endif
    @if ($booking->start_date_time)
        <div class="row"><span>{{ __('booking.payment_link_date') }}</span><span>{{ \Carbon\Carbon::parse($booking->start_date_time)->format('Y-m-d H:i') }}</span></div>
    @endif

    @if ($status === 'paid')
        <div class="msg ok" style="margin-top:22px">{{ __('booking.payment_link_page_paid') }}</div>
    @elseif ($status === 'expired')
        <div class="msg warn" style="margin-top:22px">{{ __('booking.payment_link_page_expired') }}</div>
    @elseif ($status !== 'pending')
        <div class="msg warn" style="margin-top:22px">{{ __('booking.payment_link_page_cancelled') }}</div>
    @else
        <div class="total">{{ number_format($link->amount, 2) }} {{ $link->currency === 'SAR' ? 'ر.س' : $link->currency }}</div>
        <form method="POST" action="{{ route('booking-payment-link.pay', $link->token) }}">
            @csrf
            <button class="btn" type="submit" name="brand" value="VISA">{{ __('booking.payment_link_pay_visa') }}</button>
            <button class="btn alt" type="submit" name="brand" value="MADA">{{ __('booking.payment_link_pay_mada') }}</button>
        </form>
    @endif
</div>
</body>
</html>
