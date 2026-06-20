<?php


use App\Http\Controllers\Auth\Trait\API\AuthController;
use App\Http\Controllers\Api\BookingsController;
use App\Http\Controllers\Backend\API\AddressController;
use App\Http\Controllers\Backend\API\BranchController;
use App\Http\Controllers\Backend\API\DashboardController;
use App\Http\Controllers\Backend\API\NotificationsController;
use App\Http\Controllers\Backend\API\SettingController;
use App\Http\Controllers\Backend\API\UserApiController;
use App\Http\Controllers\GiftCardController;
use App\Http\Controllers\PackageBookingController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BookingCartController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SystemUtilityController;
use App\Services\Payment\Strategies\TabbyPaymentStrategy;
use App\Services\Payment\Strategies\TamaraPaymentStrategy;
use App\Services\Payment\Strategies\UrPayPaymentStrategy;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PackageDetailsController;
use App\Http\Controllers\Backend\UserController;
use App\Http\Controllers\Api\AdController;
use App\Http\Controllers\Api\VartextController;
use App\Http\Controllers\Api\CategoriesController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\JavnaWebhookController;
use App\Http\Controllers\Api\LoyaltyController;
use App\Http\Controllers\Api\MobileCartController;
use App\Http\Controllers\Api\PackagesController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\OuroffersController;
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

Route::match(['get', 'post'], '/webhooks/javna/whatsapp', [JavnaWebhookController::class, 'whatsapp'])->name('webhooks.javna.whatsapp');

Route::prefix('var')->group(function () {
    Route::controller(AdController::class)->group(function () {
        Route::get('/AD', 'index');
        Route::get('/gift-card-images', 'giftCardImages');
    });

    Route::controller(VartextController::class)->group(function () {
        Route::get('/text', 'index');
    });
});

Route::prefix('Home')->group(function () {
    Route::controller(CategoriesController::class)->group(function () {
        Route::get('/categories', 'index');
    });

    Route::controller(PackagesController::class)->group(function () {
        Route::get('/packages', 'index');
    });
});

Route::prefix('shop')->group(function () {
    Route::controller(ShopController::class)->group(function () {
        Route::get('/', 'index');
    });
});

Route::prefix('Ouroffers')->group(function () {
    Route::controller(OuroffersController::class)->group(function () {
        Route::get('/', 'index');
    });
});

Route::controller(LoyaltyController::class)->group(function () {
    Route::get('/loyalty/point-value', 'index');
});

Route::controller(CouponController::class)->group(function () {
    Route::get('/validate-coupon', 'validateCoupon');
    Route::get('/validate-invoice-coupon', 'validateInvoiceCoupon');
    Route::get('/available-coupons', 'availableCoupons');
});


Route::controller(BranchController::class)->group(function () {
    Route::get('branch-list', 'branchList');
});

// Branch Routes
Route::prefix('branches')->group(function () {
    Route::controller(BranchController::class)->group(function () {
        Route::get('/', 'branchList');
        Route::get('{id}', 'branchDetails');
        Route::get('{id}/services', 'branchService');
        Route::get('{id}/reviews', 'branchReviews');
        Route::get('{id}/employees', 'branchEmployee');
        Route::get('{id}/gallery', 'branchGallery');
        Route::get('{id}/config', 'branchConfig');
        Route::post('{id}/assign', 'assign_update');
        Route::get('{id}/available-dates', 'getAvailableDates');
    });
});

Route::controller(BranchController::class)->group(function () {
    Route::post('verify-slot', 'verifySlot');
});

Route::prefix('services')->group(function () {
    Route::controller(ServiceController::class)->group(function () {
        Route::get('/branches/{id}', 'servicesbranches');
    });
});

Route::controller(BookingsController::class)->group(function () {
    Route::get('/States', 'States');
    Route::get('/branchs/{id}', 'branchs');
    Route::get('/service-groups', 'getServiceGroups');
    Route::get('/services/{serviceGroupId}/{branchId}/bookings', 'getServicesByGroup');
    Route::get('/staff', 'getstaff');
    Route::get('/available/{date}/{staffId}', 'getAvailableTimes');
});


Route::controller(PackageBookingController::class)->group(function () {
    Route::get('/payment-success', 'handlePaymentResult');
});

Route::middleware('auth:sanctum')->controller(SystemUtilityController::class)->group(function () {
    Route::get('/user', 'currentUser');
});

Route::controller(AuthController::class)->group(function () {
    Route::post('register', 'sendRegisterOtp');
    Route::post('verify-register-otp', 'verifyRegisterOtp');

    Route::post('resend-register-otp', 'resendRegisterOtp');
    
    Route::post('login', 'login');
    Route::post('verify-login-otp', 'verifyLoginOtp');

    Route::post('resend-login-otp', 'resendLoginOtp');

    Route::get('logout', 'logout');
});


Route::controller(DashboardController::class)->group(function () {
    Route::get('dashboard-detail', 'dashboardDetail');
});

Route::controller(BranchController::class)->group(function () {
    Route::get('base-branches', 'baseBranches');
    Route::get('branch-configuration', 'branchConfig');
    Route::get('branch-detail', 'branchDetails');
    Route::get('branch-service', 'branchService');
    Route::get('branch-review', 'branchReviews');
    Route::get('branch-employee', 'branchEmployee');
    Route::get('branch-gallery', 'branchGallery');
});


Route::prefix('gift-cards')->group(function () {
    Route::controller(GiftCardController::class)->group(function () {
        Route::post('/', 'store');
    });
});

Route::controller(GiftCardController::class)->group(function () {
    Route::get('/success-py-gift', 'handlePaymentResult');
});

Route::controller(BookingCartController::class)->group(function () {
    Route::get('/success-py-invoice', 'handlePaymentResult')->name('api.cart.payment.success');
});

// Mobile payment callbacks (public)
Route::get('/payments/tabby/success/{invoice?}', [TabbyPaymentStrategy::class, 'callback'])->name('api.tabby.success');
Route::get('/payments/tabby/fail/{invoice?}', [TabbyPaymentStrategy::class, 'fail'])->name('api.tabby.fail');
Route::get('/payments/tabby/cancel/{invoice?}', [TabbyPaymentStrategy::class, 'cancel'])->name('api.tabby.cancel');
Route::match(['get', 'post'], '/payments/urpay/success', [UrPayPaymentStrategy::class, 'success'])->name('api.urpay.success');
Route::match(['get', 'post'], '/payments/urpay/failure', [UrPayPaymentStrategy::class, 'failure'])->name('api.urpay.failure');
Route::match(['get', 'post'], '/payments/urpay/cancel', [UrPayPaymentStrategy::class, 'cancel'])->name('api.urpay.cancel');
Route::get('/payments/tamara/success', [TamaraPaymentStrategy::class, 'success'])->name('api.tamara.success');
Route::get('/payments/tamara/failure', [TamaraPaymentStrategy::class, 'failure'])->name('api.tamara.failure');
Route::get('/payments/tamara/cancel', [TamaraPaymentStrategy::class, 'cancel'])->name('api.tamara.cancel');


Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::controller(BranchController::class)->group(function () {
        Route::post('branch/assign/{id}', 'assign_update');
        Route::post('verify-slot', 'verifySlot');
    });

    Route::apiResource('branch', BranchController::class);
    Route::apiResource('user', UserApiController::class);
    Route::apiResource('setting', SettingController::class);
    Route::apiResource('notification', NotificationsController::class);
    Route::controller(NotificationsController::class)->group(function () {
        Route::get('notification-list', 'notificationList');
    });

    Route::controller(DashboardController::class)->group(function () {
        Route::get('gallery-list', 'globalGallery');
        Route::get('search-list', 'searchList');
    });

    Route::controller(AuthController::class)->group(function () {
        Route::post('update-profile', 'updateProfile');
        Route::get('profile-details', 'profileDetails');
        Route::post('profile-update', 'updateMyProfile');
        Route::post('change-password', 'changePassword');
        Route::post('delete-account', 'deleteAccount');
    });

    Route::controller(UserController::class)->group(function () {
        Route::post('change-password', 'change_password')->name('change_password');
    });

    Route::controller(AddressController::class)->group(function () {
        Route::post('add-address', 'store');
        Route::get('address-list', 'AddressList');
        Route::get('remove-address', 'RemoveAddress');
        Route::post('edit-address', 'EditAddress');
    });

    Route::controller(MobileCartController::class)->group(function () {
        Route::get('/mobile/cart', 'index');
        Route::post('/mobile/cart/bookings', 'storeBooking');
        Route::post('/mobile/cart/gift-cards', 'storeGiftCard');
    });

    Route::controller(BookingCartController::class)->group(function () {
        Route::post('/cart/products/{id}', 'addToCart');
        Route::delete('/cart/{id}', 'destroy');
        Route::post('/cart-pay', 'cartPay');
        Route::get('/wallet-loyalty-balance', 'walletLoyaltyBalance');
        Route::get('/loyallety', 'balance');
    });

    Route::controller(PaymentController::class)->group(function () {
        Route::post('/payment-chanal', 'payment');
    });

    Route::controller(BookingController::class)->group(function () {
        Route::post('/bookings', 'store');
    });

    Route::controller(PackageDetailsController::class)->group(function () {
        Route::get('/details/{id}', 'show');
    });

    Route::controller(PackageBookingController::class)->group(function () {
        Route::get('/pay-now', 'createPayment');
    });
});

Route::controller(SettingController::class)->group(function () {
    Route::post('app-configuration', 'appConfiguraton');
});
