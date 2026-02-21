<?php


use App\Http\Controllers\Auth\Trait\API\AuthController;
use App\Http\Controllers\Api\BookingsController;
use App\Http\Controllers\Backend\API\AddressController;
use App\Http\Controllers\Backend\API\BranchController;
use App\Http\Controllers\Backend\API\DashboardController;
use App\Http\Controllers\Backend\API\NotificationsController;
use App\Http\Controllers\Backend\API\SettingController;
use App\Http\Controllers\Backend\API\UserApiController;
use App\Http\Controllers\CalanderBookingController;
use App\Http\Controllers\GiftCardController;
use App\Http\Controllers\HomeBookingController;
use App\Http\Controllers\BookingCartController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SaloneBookController;
use App\Http\Controllers\Backend\UserController;
use App\Http\Controllers\Api\AdController;
use App\Http\Controllers\Api\VartextController;
use App\Http\Controllers\Api\CategoriesController;
use App\Http\Controllers\Api\PackagesController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\OuroffersController;
use App\Models\Ad;
use App\Models\Vartext;
use App\Http\Controllers;
use Modules\Service\Http\Controllers\Backend\API\ServiceController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('var')->group(function () {
    Route::get('/AD', [AdController::class, 'index']);
    Route::get('/text', [VartextController::class, 'index']);
});

Route::prefix('Home')->group(function () {
    Route::get('/categories', [CategoriesController::class, 'index']);
    Route::get('/packages', [PackagesController::class, 'index']);
});

Route::prefix('shop')->group(function () {
    Route::get('/', [ShopController::class, 'index']);
});

Route::prefix('Ouroffers')->group(function () {
    Route::get('/', [OuroffersController::class, 'index']);
});


Route::get('branch-list', [BranchController::class, 'branchList']);

// Branch Routes
Route::prefix('branches')->group(function () {
    Route::get('/', [BranchController::class, 'branchList']);
    Route::get('{id}', [BranchController::class, 'branchDetails']);
    Route::get('{id}/services', [BranchController::class, 'branchService']);
    Route::get('{id}/reviews', [BranchController::class, 'branchReviews']);
    Route::get('{id}/employees', [BranchController::class, 'branchEmployee']);
    Route::get('{id}/gallery', [BranchController::class, 'branchGallery']);
    Route::get('{id}/config', [BranchController::class, 'branchConfig']);
    Route::post('{id}/assign', [BranchController::class, 'assign_update']);
     Route::get('{id}/available-dates', [BranchController::class, 'getAvailableDates']);
});
Route::post('verify-slot', [BranchController::class, 'verifySlot']);

Route::prefix('gift-cards')->group(function () {
    Route::get('/', [GiftCardController::class, 'index'])->name('gift.page');
    Route::post('/', [GiftCardController::class, 'store'])->name('gift.create');
    Route::get('/payment-result', [GiftCardController::class, 'handlePaymentResult'])->name('gift.payment_result');
});



Route::prefix('services')->group(function () {
    Route::get('/branches/{id}', [ServiceController::class, 'servicesbranches']);
});

Route::get('user-detail', [AuthController::class, 'userDetails']);
Route::get('services', [CalanderBookingController::class, 'getservices']);
Route::post('/Calander-bookings-new', [CalanderBookingController::class, 'store']);
Route::get('/employees', [CalanderBookingController::class, 'emplouee']);
// routes/api.php
Route::put('/booking-carts/{id}', [CalanderBookingController::class, 'update']);
Route::delete('/booking-carts/{id}', [CalanderBookingController::class, 'destroy']);
Route::get('/booking-carts/by-time', [CalanderBookingController::class, 'getAllByTime']);
Route::get('/booking-carts/by-day', [CalanderBookingController::class, 'getAllByDay']);


Route::get('/States', [BookingsController::class, 'States']);
Route::get('/branchs/{id}', [BookingsController::class, 'branchs']);
Route::get('/service-groups', [BookingsController::class, 'getServiceGroups']);
Route::get('/services/{serviceGroupId}/{branchId}/bookings', [BookingsController::class, 'getServicesByGroup']);
Route::get('/staff', [BookingsController::class, 'index']);
Route::get('/available/{date}/{staffId}', [BookingsController::class, 'getAvailableTimes']);


Route::get('/payment-success', [HomeBookingController::class, 'handlePaymentResult']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::controller(AuthController::class)->group(function () {
    Route::post('register', 'register');
    // or ||
    Route::post('send-register-otp', 'sendRegisterOtp');
    
    Route::post('resend-register-otp', 'resendRegisterOtp');
    
    Route::post('verify-register-otp', 'verifyRegisterOtp');
    
    Route::post('login', 'login');
    
    Route::post('social-login', 'socialLogin');
    Route::post('forgot-password', 'forgotPassword');
    Route::get('logout', 'logout');
});


Route::get('dashboard-detail', [DashboardController::class, 'dashboardDetail']);
Route::get('base-branches', [BranchController::class, 'baseBranches']);
Route::get('branch-configuration', [BranchController::class, 'branchConfig']);
Route::get('branch-detail', [BranchController::class, 'branchDetails']);
Route::get('branch-service', [BranchController::class, 'branchService']);
Route::get('branch-review', [BranchController::class, 'branchReviews']);
Route::get('branch-employee', [BranchController::class, 'branchEmployee']);
Route::get('branch-gallery', [BranchController::class, 'branchGallery']);


Route::prefix('gift-cards')->group(function () {
    Route::get('/', [GiftCardController::class, 'index']);
    Route::post('/', [GiftCardController::class, 'store']);
    Route::get('/payment-result', [GiftCardController::class, 'handlePaymentResult']);
});

Route::get('/success-py-gift', [GiftCardController::class, 'handlePaymentResult']);
Route::get('/success-py-invoice', [BookingCartController::class, 'handlePaymentResult']);


Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::post('branch/assign/{id}', [BranchController::class, 'assign_update']);
    Route::apiResource('branch', BranchController::class);
    Route::apiResource('user', UserApiController::class);
    Route::apiResource('setting', SettingController::class);
    Route::apiResource('notification', NotificationsController::class);
    Route::get('notification-list', [NotificationsController::class, 'notificationList']);
    Route::get('gallery-list', [DashboardController::class, 'globalGallery']);
    Route::get('search-list', [DashboardController::class, 'searchList']);
    Route::post('update-profile', [AuthController::class, 'updateProfile']);
    Route::post('change-password', [UserController::class, 'change_password'])->name('change_password');
    Route::post('change-password', [AuthController::class, 'changePassword']);
    Route::post('delete-account', [AuthController::class, 'deleteAccount']);

    Route::post('add-address', [AddressController::class, 'store']);
    Route::get('address-list', [AddressController::class, 'AddressList']);
    Route::get('remove-address', [AddressController::class, 'RemoveAddress']);
    Route::post('edit-address', [AddressController::class, 'EditAddress']);

    Route::post('verify-slot', [BranchController::class, 'verifySlot']);

    Route::get('/cart', [BookingCartController::class, 'index']);
    Route::post('/cart', [BookingCartController::class, 'store']);
    Route::delete('/cart/{id}', [BookingCartController::class, 'destroy']);
    Route::post('/cart-pay', [BookingCartController::class, 'cartPay']);
    Route::get('/loyallety', [BookingCartController::class, 'balance']);
    Route::post('/bookings', [HomeBookingController::class, 'store']);
    Route::get('/details/{id}', [SaloneBookController::class, 'show']);
    Route::get('/pay-now', [HomeBookingController::class, 'createPayment']);
});
Route::post('app-configuration', [SettingController::class, 'appConfiguraton']);
