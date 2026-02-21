<?php

use Illuminate\Support\Facades\Route;
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

Route::get('add/balance-to-wallet', [WalletController::class, 'addbalance'])->name('wallet.addbalance');
Route::get('/add/balance/callback', [WalletController::class, 'callback'])->name('addbalance.callback');