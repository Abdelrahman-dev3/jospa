<?php

use App\Http\Controllers\Backend\PaymentRequestController;
use App\Http\Controllers\PaymentRequestPublicController;
use Illuminate\Support\Facades\Route;

/*
| Mini invoices & payment links ("الفواتير وروابط الدفع").
| Loaded from routes/web.php, so the "web" middleware group applies.
*/

// Dashboard (login + permission checks in the controller).
Route::prefix('app/payment-requests')
    ->name('backend.payment-requests.')
    ->middleware('auth')
    ->group(function () {
        Route::get('/', [PaymentRequestController::class, 'index'])->name('index');
        Route::post('/', [PaymentRequestController::class, 'store'])->middleware('throttle:30,1')->name('store');
        Route::post('/{paymentRequest}/refresh', [PaymentRequestController::class, 'refresh'])->whereNumber('paymentRequest')->middleware('throttle:30,1')->name('refresh');
        Route::post('/{paymentRequest}/cancel', [PaymentRequestController::class, 'cancel'])->whereNumber('paymentRequest')->name('cancel');
        Route::post('/{paymentRequest}/odoo-sync', [PaymentRequestController::class, 'odooSync'])->whereNumber('paymentRequest')->middleware('throttle:10,1')->name('odoo-sync');
        Route::get('/{paymentRequest}/invoice.pdf', [PaymentRequestController::class, 'invoicePdf'])->whereNumber('paymentRequest')->name('invoice-pdf');
    });

// Public customer pages (no login; secret token in the URL).
Route::match(['get', 'post'], 'pay/invoice/return/{attempt}', [PaymentRequestPublicController::class, 'return'])
    ->whereUuid('attempt')
    ->middleware('throttle:30,1')
    ->name('payment-request.return');

Route::get('pay/invoice/{token}', [PaymentRequestPublicController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->middleware('throttle:30,1')
    ->name('payment-request.show');

Route::get('pay/invoice/{token}/invoice.pdf', [PaymentRequestPublicController::class, 'invoicePdf'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->middleware('throttle:30,1')
    ->name('payment-request.invoice-pdf');

Route::post('pay/invoice/{token}', [PaymentRequestPublicController::class, 'pay'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->middleware('throttle:10,1')
    ->name('payment-request.pay');
