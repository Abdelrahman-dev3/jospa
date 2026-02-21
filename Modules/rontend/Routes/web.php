<?php

use Illuminate\Support\Facades\Route;
use Modules\Frontend\Http\Controllers\FrontendController;
use App\Http\Controllers\PaymentController;

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

Route::group(['prefix' => ''], function () {
    Route::get('/', [FrontendController::class, 'index'])->name('frontend.home');
    Route::get('/about', [FrontendController::class, 'about'])->name('frontend.about');
    Route::get('/services', [FrontendController::class, 'services'])->name('frontend.services');
    Route::get('/services/category/{id}', [FrontendController::class, 'categoryDetails'])->name('frontend.category.details');
    Route::get('/services/{id}', [FrontendController::class, 'serviceDetails'])->name('frontend.service.details');
    Route::get('/product/{id}', [FrontendController::class, 'productDetails'])->name('frontend.product.details');
    Route::get('/contact', [FrontendController::class, 'contact'])->name('frontend.contact');
    Route::get('/branches', [FrontendController::class, 'branches'])->name('frontend.branches');
    Route::get('/Ouroffers', [FrontendController::class, 'Ouroffers'])->name('frontend.Ouroffers');
    Route::get('/TermsAndConditions', [FrontendController::class, 'TermsAndConditions'])->name('frontend.TermsAndConditions');
    Route::get('/Packages', [FrontendController::class, 'Packages'])->name('frontend.Packages');
    Route::get('/Shop', [FrontendController::class, 'Shop'])->name('frontend.Shop');
    Route::middleware('auth')->group(function () {
        Route::group(['prefix' => 'payment'], function () {
            Route::get('/', [PaymentController::class, 'index'])->name('paymentMethods');
        });
        Route::get('/become-affiliate', [FrontendController::class, 'becomeAffiliate'])
            ->name('frontend.become.affiliate');

        Route::post('/become-affiliate', [FrontendController::class, 'activateAffiliate'])
            ->name('frontend.become.affiliate.submit');
    });
});
