<?php

use Illuminate\Support\Facades\Route;
use Modules\Frontend\Http\Controllers\FrontendController;

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
    Route::get('/contact', [FrontendController::class, 'contact'])->name('frontend.contact');
});
