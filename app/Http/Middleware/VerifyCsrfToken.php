<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
protected $except = [
    'api/quick-booking/*',
    'login',
    'register',
    'urpay/success',
    'urpay/failure',
    'urpay/cancel',
    'wallet/app-topup/return/*',
    // HyperPay may POST the shopper back; the handler ignores the payload and verifies server-side.
    'pay/invoice/return/*',

];
}
