<?php

use Illuminate\Support\Facades\Route;
use Modules\Booking\Http\Controllers\Backend\BookingPaymentLinkController;
use Modules\Booking\Http\Controllers\Backend\BookingsController;
use Modules\Booking\Http\Controllers\Backend\HomeBookingsController;
use Modules\Booking\Http\Controllers\BookingPaymentLinkPublicController;

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

/*
*
* Backend Routes
*
* --------------------------------------------------------------------
*/
Route::group(['prefix' => 'app', 'as' => 'backend.', 'middleware' => ['auth']], function () {
    /*
    * These routes need view-backend permission
    * (good if you want to allow more than one group in the backend,
    * then limit the backend features by different roles or permissions)
    *
    * Note: Administrator has all permissions so you do not have to specify the administrator role everywhere.
    */

    /*
     *
     *  Backend Bookings Routes
     *
     * ---------------------------------------------------------------------
     */
    Route::group(['prefix' => 'bookings', 'as' => 'bookings.'], function () {
        Route::get('/index_data', [BookingsController::class, 'index_data'])->name('index_data');
        Route::get('/list_view', [BookingsController::class, 'list_view'])->name('list_view');
        Route::get('/index_list', [BookingsController::class, 'index_list'])->name('index_list');
        Route::get('/services-index_list', [BookingsController::class, 'services_index_list'])->name('services_index_list');
        Route::get('/trashed', [BookingsController::class, 'trashed'])->name('trashed');
        Route::patch('/trashed/{id}', [BookingsController::class, 'restore'])->name('restore');
        Route::get('/{id}/activity-log', [BookingsController::class, 'activityLog'])->name('activity_log');
        Route::post('/update-status/{id}', [BookingsController::class, 'updateStatus'])->name('updateStatus');
        Route::post('/update-payment-status/{id}', [BookingsController::class, 'updatePaymentStatus'])->name('updatePaymentStatus');
        Route::post('/employee-order', [BookingsController::class, 'updateEmployeeOrder'])->name('employee_order');
        Route::post('bulk-action', [BookingsController::class, 'bulk_action'])->name('bulk_action');
        Route::get('slots', [BookingsController::class, 'booking_slots'])->name('slots');
        Route::post('payment', [BookingsController::class, 'booking_payment'])->name('payment');
        Route::get('payment-create', [BookingsController::class, 'payment_create'])->name('payment_create');
        Route::put('booking-payment/{booking_id}', [BookingsController::class, 'booking_payment'])->name('booking_payment');
        Route::put('booking-payment-update/{booking_transaction_id}', [BookingsController::class, 'booking_payment_update'])->name('booking_payment_update');
        Route::put('{booking_id}/checkout', [BookingsController::class, 'checkout'])->name('checkout');
        Route::post('stripe-payment', [BookingsController::class, 'stripe_payment'])->name('stripe_payment');
        Route::get('payment_success/{booking_transaction_id}', [BookingsController::class, 'payment_success'])->name('payment_success');
        Route::get('export', [BookingsController::class, 'export'])->name('export');
        Route::get('{booking}/payment-link', [BookingPaymentLinkController::class, 'show'])->name('payment_link.show')->whereNumber('booking');
        Route::post('{booking}/payment-link', [BookingPaymentLinkController::class, 'store'])->name('payment_link.store')->whereNumber('booking');
        Route::post('{booking}/payment-link/refresh', [BookingPaymentLinkController::class, 'refresh'])->name('payment_link.refresh')->whereNumber('booking');

        Route::group(['prefix' => 'home-bookings', 'as' => 'home_bookings.'], function () {
            Route::get('service-groups', [HomeBookingsController::class, 'serviceGroups'])->name('service_groups');
            Route::get('services', [HomeBookingsController::class, 'services'])->name('services');
            Route::get('staff', [HomeBookingsController::class, 'staff'])->name('staff');
            Route::post('/', [HomeBookingsController::class, 'store'])->name('store');
        });
    });
    Route::get('booking-invoice', [BookingsController::class, 'viewInvoice'])->name('bookings.invoice');
    Route::get('booking-invoice-download', [BookingsController::class, 'downloadInvoice'])->name('bookings.downloadinvoice');
    Route::get('bookings-table-view', [BookingsController::class, 'datatable_view'])->name('bookings.datatable_view');
    Route::resource('bookings', BookingsController::class);
});

// Public customer payment page (token-protected, no login)
Route::get('pay/booking/{token}', [BookingPaymentLinkPublicController::class, 'show'])->middleware('throttle:30,1')->name('booking-payment-link.show');
Route::post('pay/booking/{token}', [BookingPaymentLinkPublicController::class, 'pay'])->middleware('throttle:10,1')->name('booking-payment-link.pay');
