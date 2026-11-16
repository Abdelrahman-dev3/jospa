<?php

use Illuminate\Support\Facades\Route;
use Modules\Wallet\Http\Controllers\WalletAppTopupController;
use Modules\Wallet\Http\Controllers\WalletController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(['prefix' => 'app', 'middleware' => 'auth'], function () {
    Route::resource('wallet', WalletController::class)->names('wallet');

    Route::get('wallet-history/{id}', [WalletController::class, 'walletHistory'])->name('wallet.history');
    Route::get('wallet-history-data', [WalletController::class, 'walletHistoryData'])->name('wallet.history-data');
});

Route::get('add/balance-to-wallet', [WalletController::class, 'addbalance'])->middleware('auth')->name('wallet.addbalance');
Route::get('/add/balance/hyperpay/checkout', [WalletController::class, 'hyperpayCheckout'])->name('wallet.hyperpay.checkout');
Route::match(['get', 'post'], '/add/balance/callback', [WalletController::class, 'callback'])->name('addbalance.callback');

// Mobile-app wallet top-up pages (started by POST /api/wallet/topup/init).
Route::prefix('wallet/app-topup')->name('wallet.app-topup.')->group(function () {
    Route::get('/pay/{attempt}', [WalletAppTopupController::class, 'pay'])->name('pay');
    Route::match(['get', 'post'], '/return/{attempt}', [WalletAppTopupController::class, 'return'])
        ->middleware('throttle:30,1')
        ->name('return');
    Route::get('/done', [WalletAppTopupController::class, 'done'])->name('done');
});
