<?php
    use App\Http\Controllers\AdsController;
    use App\Http\Controllers\BookingsController;
    use App\Http\Controllers\BookingCartController;
    use App\Http\Controllers\ContactMessageController;
    use App\Http\Controllers\GiftCardController;
    use App\Http\Controllers\GiftController;
    use App\Http\Controllers\HomeBookingController;
    use App\Http\Controllers\InvoiceController;
    use App\Http\Controllers\LanguageController;
    use App\Http\Controllers\LoyaltyController;
    use App\Http\Controllers\MobileVerificationController;
    use App\Http\Controllers\ModuleController;
    use App\Http\Controllers\offersController;
    use App\Http\Controllers\PaymentchanalController;
    use App\Http\Controllers\PermissionController;
    use App\Http\Controllers\ReportsController;
    use App\Http\Controllers\RoleController;
    use App\Http\Controllers\RolePermission;
    use App\Http\Controllers\RejectController;
    use App\Http\Controllers\SearchController;
    use App\Http\Controllers\SaloneBookController;
    use App\Http\Controllers\SignController;
    use App\Http\Controllers\SmsController;
    use App\Http\Controllers\TermsAndConditionsController;
    use App\Http\Controllers\TaqnyatSmsController;
    use App\Http\Controllers\TextController;
    use App\Http\Controllers\WheelController;
    
    use App\Http\Controllers\Backend\BackendController;
    use App\Http\Controllers\Backend\BackupController;
    use App\Http\Controllers\Backend\BranchController;
    use App\Http\Controllers\Backend\NotificationsController;
    use App\Http\Controllers\Backend\SettingController;
    use App\Http\Controllers\Backend\UserController;
    use App\Http\Controllers\Backend\UsersController;
    
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Facades\Artisan;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Storage;
    
    use App\Services\Payment\Strategies\CardPaymentStrategy;
    use App\Services\Payment\Strategies\TabbyPaymentStrategy;
    use App\Services\Payment\Strategies\TamaraPaymentStrategy;
    
    use App\Models\Branch;
    use App\Models\BookingCart;
    
    use Modules\Affiliate\Http\Controllers\AffiliateAdminController;
    use Modules\Employee\Http\Controllers\Backend\EmployeesController;
    
    use App\Providers\RouteServiceProvider;
    
    
    
    
    Route::get('/test-upload', function () {
        Storage::disk('public')->put('test.txt', 'hello from laravel');
        return Storage::disk('public')->path('test.txt');
    });


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


    Route::get('/signup', [SignController::class, 'index'])->name('signup');
    Route::post('/signup', [SignController::class, 'store'])->name('signup.store');
    Route::get('/signin', [SignController::class, 'login'])->name('signin');
    Route::post('/signin/verify', [SignController::class, 'verify'])->name('signin.verify');
    Route::get('verify-mobile', [MobileVerificationController::class, 'showVerifyForm'])->name('verify.mobile');
    Route::post('verify-otp', [MobileVerificationController::class, 'verifyOtp'])->name('verify.otp');
    Route::post('resend-otp', [MobileVerificationController::class, 'resendOtp'])->name('resend.otp');
    Route::get('send-OTP', [SignController::class, 'showVerifyForm'])->name('login.verify.form');
    Route::post('verify-send-otp', [SignController::class, 'verifyOTP'])->name('verify.send.otp');


    Route::get('/sms-messages', [TaqnyatSmsController::class, 'index'])->name('app.sms');
    Route::post('/store', [TaqnyatSmsController::class, 'store'])->name('store');
    Route::post('/send-test', [TaqnyatSmsController::class, 'sendTestMessage'])->name('send-test');


    Route::get('/salonService' , [BookingsController::class , 'salon'])->name('salon.create');
    Route::get('/HomeService' , [BookingsController::class , 'home'])->name('home.create');
    Route::get('/giffte' , [GiftCardController::class, 'index'])->name('gift.page');
    Route::get('/details/{id}', [SaloneBookController::class, 'show'])->name('home.details');

    
    Route::controller(PaymentchanalController::class)->group(function () {
        Route::post('/payment-chanal', 'payment')->name('payment-chanal');
        Route::get('tabby/success/{invoice}', 'tabbySuccess')->name('tabby.success');
        Route::get('tabby/fail/{invoice}', 'tabbyFail')->name('tabby.fail');
        Route::get('tabby/cancel/{invoice}', 'tabbyCancel')->name('tabby.cancel');

        Route::get('/tamara/success', [TamaraPaymentStrategy::class, 'success'])->name('tamara.success');
        Route::get('/tamara/failure', [TamaraPaymentStrategy::class, 'failure'])->name('tamara.failure');
        Route::get('/tamara/cancel', [TamaraPaymentStrategy::class, 'cancel'])->name('tamara.cancel');
    });
    Route::get('/payment/callback', [CardPaymentStrategy::class, 'callback'])->name('tap.callback');
    
    
    Route::post('/staff/working-hours/{id}', [EmployeesController::class, 'store_working_houer'])->name('staff.working-hours.store');
    Route::get('/loyalety' , [LoyaltyController::class , 'loyalety'])->name('home.loyalety');
    
    // Use when user not loggin
    Route::post('/cart', [BookingCartController::class, 'store'])->name('cart.store');
    Route::get('/gift-cards', [GiftCardController::class, 'store'])->name('gift.create');

    Route::middleware('auth')->group(function () {


        Route::get('/cart', [BookingCartController::class, 'index'])->name('cart.page');
        Route::delete('/cart/{id}', [BookingCartController::class, 'destroy'])->name('cart.destroy');
        Route::delete('p/cart/{id}', [BookingCartController::class, 'destroy_product'])->name('p.cart.destroy');
        Route::delete('g/cart/{id}', [BookingCartController::class, 'destroy_gift'])->name('g.cart.destroy');
        Route::delete('/cart/destroy/All', [BookingCartController::class, 'destroy_All'])->name('cart.destroyAll');
    
    
        Route::get('/admin/contact-messages', [ContactMessageController::class, 'index'])->name('contact.index');
        Route::post('/admin/contact-messages/{id}/reply', [ContactMessageController::class, 'reply'])->name('admin.contact-messages.reply');
        Route::post('/admin/contact-messages/bulk-action', [ContactMessageController::class, 'bulkAction'])->name('admin.contact-messages.bulk-action');
        Route::post('/contact', [ContactMessageController::class, 'store'])->name('contact.store');
    
    
        Route::get('/profile', [SignController::class, 'profile'])->name('profile');
        Route::put('/profile/{id}/update', [SignController::class, 'update'])->name('profile.update');
        Route::post('/logout', [SignController::class, 'logout'])->name('logout');    
    
        Route::get('/success-py-invoice', [BookingCartController::class, 'handlePaymentResult']);
        
        
        Route::get('/loyalty-points/check', [BookingCartController::class, 'checkLoyaltyPoints'])->name('loyalty.check');
    
        
        Route::post('/cart-pay', [BookingCartController::class, 'cartPay'])->name('cart.payment');
    });
    
    Route::get('all/branchs/', [HomeBookingController::class, 'allbranchs']);
    Route::post('/bookings', [HomeBookingController::class, 'store'])->name('bookings.store');
    Route::get('/cart/add/{id}', [BookingCartController::class, 'addToCart'])->name('cart.add');
    Route::post('/package-booking', [HomeBookingController::class, 'storePackageBooking'])->name('package.booking.store');
    Route::get('/complete-package-booking', [HomeBookingController::class, 'completePackageBooking'])->name('package.booking.complete');


    Route::get('lang/{locale}', function ($locale) {
        if (in_array($locale, ['en', 'ar'])) {
            session(['locale' => $locale]);
        }
        return redirect()->back();
    })->name('language.switch');

    // Clear config cache
    Route::get('/clear-config', function () {
        Artisan::call('config:clear');
        return 'Config cache cleared!';
    });

    // Clear application cache
    Route::get('/clear-cache', function () {
        Artisan::call('cache:clear');
        return 'Application cache cleared!';
    });

    // Clear route cache
    Route::get('/clear-route', function () {
        Artisan::call('route:clear');
        return 'Route cache cleared!';
    });
    Route::get('/modules-list', function () {
        Artisan::call('module:list');
        $output = Artisan::output();
        return nl2br($output);
    });

    // Clear compiled views
    Route::get('/clear-view', function () {
        Artisan::call('view:clear');
        return 'View cache cleared!';
    });

    // Clear all caches together
    Route::get('/clear-all', function () {
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        return 'All caches cleared!';
    });



    // Auth Routes
    require __DIR__.'/auth.php';
    
    Route::get('storage-link', function () {
        return Artisan::call('storage:link');
    });

    Route::get('/admin', function () {
        if (auth()->user()->hasRole('employee')) {
            return redirect(RouteServiceProvider::EMPLOYEE_LOGIN_REDIRECT);
        } else {
            return redirect(RouteServiceProvider::HOME);
        }
    })->middleware('auth');

    Route::group(['middleware' => ['auth']], function () {
        Route::get('notification-list', [NotificationsController::class, 'notificationList'])->name('notification.list');
        Route::get('notification-counts', [NotificationsController::class, 'notificationCounts'])->name('notification.counts');
    });

    // Language Switch
    Route::get('language/{language}', [LanguageController::class, 'switch'])->name('language.switch');
    Route::group(['prefix' => 'app', 'middleware' => 'auth'], function () {
        Route::post('set-user-setting', [BackendController::class, 'setUserSetting'])->name('backend.setUserSetting');

        Route::group(['as' => 'backend.', 'middleware' => ['auth']], function () {
            Route::get('get_search_data', [SearchController::class, 'get_search_data'])->name('get_search_data');

            // Sync Role & Permission
            Route::get('/permission-role', [RolePermission::class, 'index'])->name('permission-role.list')->middleware('password.confirm');
            Route::post('/permission-role/store/{role_id}', [RolePermission::class, 'store'])->name('permission-role.store');
            Route::get('/permission-role/reset/{role_id}', [RolePermission::class, 'reset_permission'])->name('permission-role.reset');
            // Role & Permissions Crud
            Route::resource('permission', PermissionController::class);
            Route::resource('role', RoleController::class);

            Route::group(['prefix' => 'module', 'as' => 'module.'], function () {
                Route::get('index_data', [ModuleController::class, 'index_data'])->name('index_data');
                Route::post('update-status/{id}', [ModuleController::class, 'update_status'])->name('update_status');
            });

            Route::resource('module', ModuleController::class);

            /*
            *
            *  Settings Routes
            *
            * ---------------------------------------------------------------------
            */
            Route::group(['middleware' => []], function () {
                Route::get('settings/{vue_capture?}', [SettingController::class, 'index'])->name('settings')->where('vue_capture', '^(?!storage).*$');
                Route::get('settings-data', [SettingController::class, 'index_data']);
                Route::post('settings', [SettingController::class, 'store'])->name('settings.store');
                Route::post('setting-update', [SettingController::class, 'update'])->name('setting.update');
                Route::get('clear-cache', [SettingController::class, 'clear_cache'])->name('clear-cache');
                Route::post('verify-email', [SettingController::class, 'verify_email'])->name('verify-email');
            });

            /*
            *
            *  Notification Routes
            *
            * ---------------------------------------------------------------------
            */
            Route::group(['prefix' => 'notifications', 'as' => 'notifications.'], function () {
                Route::get('/', [NotificationsController::class, 'index'])->name('index');
                Route::get('/markAllAsRead', [NotificationsController::class, 'markAllAsRead'])->name('markAllAsRead');
                Route::delete('/deleteAll', [NotificationsController::class, 'deleteAll'])->name('deleteAll');
                Route::get('/{id}', [NotificationsController::class, 'show'])->name('show');
            });

            /*
            *
            *  Backup Routes
            *
            * ---------------------------------------------------------------------
            */
            Route::group(['prefix' => 'backups', 'as' => 'backups.'], function () {
                Route::get('/', [BackupController::class, 'index'])->name('index');
                Route::get('/create', [BackupController::class, 'create'])->name('create');
                Route::get('/download/{file_name}', [BackupController::class, 'download'])->name('download');
                Route::get('/delete/{file_name}', [BackupController::class, 'delete'])->name('delete');
            });

            Route::get('daily-booking-report', [ReportsController::class, 'daily_booking_report'])->name('reports.daily-booking-report');
            Route::get('daily-booking-report-index-data', [ReportsController::class, 'daily_booking_report_index_data'])->name('reports.daily-booking-report.index_data');
            Route::get('overall-booking-report', [ReportsController::class, 'overall_booking_report'])->name('reports.overall-booking-report');
            Route::get('overall-booking-report-index-data', [ReportsController::class, 'overall_booking_report_index_data'])->name('reports.overall-booking-report.index_data');
            Route::get('payout-report', [ReportsController::class, 'payout_report'])->name('reports.payout-report');
            Route::get('payout-report-index-data', [ReportsController::class, 'payout_report_index_data'])->name('reports.payout-report.index_data');
            Route::get('staff-report', [ReportsController::class, 'staff_report'])->name('reports.staff-report');
            Route::get('staff-report-index-data', [ReportsController::class, 'staff_report_index_data'])->name('reports.staff-report.index_data');

            Route::get('order-report', [ReportsController::class, 'order_report'])->name('reports.order-report');
            Route::get('order-report-index-data', [ReportsController::class, 'order_report_index_data'])->name('reports.order-report.index_data');

            Route::get('financial-report', [ReportsController::class, 'financial_report'])->name('reports.financial-report');
            Route::get('financial-report-index-data', [ReportsController::class, 'financial_report_index_data'])->name('reports.financial-report.index_data');

            Route::get('coupon-report', [ReportsController::class, 'coupon_report'])->name('reports.coupon-report');
            Route::get('coupon-report-index-data', [ReportsController::class, 'coupon_report_index_data'])->name('reports.coupon-report.index_data');
            Route::get('promotion-report', [ReportsController::class, 'promotion_report'])->name('reports.promotion-report');
            Route::get('promotion-report-index-data', [ReportsController::class, 'promotion_report_index_data'])->name('reports.promotion-report.index_data');

            Route::get('payment-transactions-report', [ReportsController::class, 'payment_transactions_report'])->name('reports.payment-transactions-report');
            Route::get('payment-transactions-report-index-data', [ReportsController::class, 'payment_transactions_report_index_data'])->name('reports.payment-transactions-report.index_data');
            
            // Review Routes
            Route::get('daily-booking-report-review', [ReportsController::class, 'daily_booking_report_review'])->name('reports.daily-booking-report-review');
            Route::get('overall-booking-report-review', [ReportsController::class, 'overall_booking_report_review'])->name('reports.overall-booking-report-review');
            Route::get('payout-report-review', [ReportsController::class, 'payout_report_review'])->name('reports.payout-report-review');
            Route::get('staff-report-review', [ReportsController::class, 'staff_report_review'])->name('reports.staff-report-review');
            Route::get('order_booking_report_review', [ReportsController::class, 'order_booking_report_review'])->name('reports.order_booking_report_review');

        });

        /*
        *
        * Backend Routes
        * These routes need view-backend permission
        * --------------------------------------------------------------------
        */

        Route::middleware(['checkInstallation'])->group(function () {

            Route::group(['as' => 'backend.', 'middleware' => ['auth']], function () {
                /**
                 * Backend Dashboard
                 * Namespaces indicate folder structure.
                 */
                Route::get('/', [BackendController::class, 'index'])->name('home');

                Route::post('set-current-branch/{branch_id}', [BackendController::class, 'setCurrentBranch'])->name('set-current-branch');
                Route::post('reset-branch', [BackendController::class, 'resetBranch'])->name('reset-branch');

                Route::group(['prefix' => ''], function () {
                    Route::get('dashboard', [BackendController::class, 'index'])->name('dashboard');

                    /**
                     * Branch Routes
                     */
                    Route::group(['prefix' => 'branch', 'as' => 'branch.'], function () {
                        Route::get('index_list', [BranchController::class, 'index_list'])->name('index_list');
                        Route::get('assign/{id}', [BranchController::class, 'assign_list'])->name('assign_list');
                        Route::post('assign/{id}', [BranchController::class, 'assign_update'])->name('assign_update');
                        Route::get('index_data', [BranchController::class, 'index_data'])->name('index_data');
                        Route::get('trashed', [BranchController::class, 'trashed'])->name('trashed');
                        Route::patch('trashed/{id}', [BranchController::class, 'restore'])->name('restore');
                        // Branch Gallery Images
                        Route::get('gallery-images/{id}', [BranchController::class, 'getGalleryImages']);
                        Route::post('gallery-images/{id}', [BranchController::class, 'uploadGalleryImages']);
                        Route::post('bulk-action', [BranchController::class, 'bulk_action'])->name('bulk_action');
                        Route::post('update-status/{id}', [BranchController::class, 'update_status'])->name('update_status');
                        Route::post('update-select-value/{id}/{action_type}', [BranchController::class, 'update_select'])->name('update_select');
                        Route::post('branch-setting', [BranchController::class, 'UpdateBranchSetting'])->name('branch_setting');
                    });
                    Route::get('branch-info', [BranchController::class, 'branchData'])->name('branchData');
                    Route::resource('branch', BranchController::class);

                    /*
                    *
                    *  Users Routes
                    *
                    * ---------------------------------------------------------------------
                    */
                    Route::group(['prefix' => 'users', 'as' => 'users.'], function () {
                        Route::get('user-list', [UserController::class, 'user_list'])->name('user_list');
                        Route::get('emailConfirmationResend/{id}', [UserController::class, 'emailConfirmationResend'])->name('emailConfirmationResend');
                        Route::post('create-customer', [UserController::class, 'create_customer'])->name('create_customer');
                        Route::post('information', [UserController::class, 'update'])->name('information');
                        Route::post('change-password', [UserController::class, 'change_password'])->name('change_password');
                        Route::get('create', [UsersController::class, 'create'])->name('create')->middleware('permission:view_role_permissions');
                        Route::post('/', [UsersController::class, 'store'])->name('store')->middleware('permission:view_role_permissions');
                    });
                });
                Route::get('my-profile/{vue_capture?}', [UserController::class, 'myProfile'])->name('my-profile')->where('vue_capture', '^(?!storage).*$');
                Route::get('my-info', [UserController::class, 'authData'])->name('authData');
                Route::post('my-profile/change-password', [UserController::class, 'change_password'])->name('change_password');
            });
        });
    });

    Route::get('/my-bookings', [SignController::class, 'myBookings'])->name('profile.my_bookings');

    Route::get('/coupon', [SignController::class, 'coupon'])->name('profile.coupon');

    Route::post('/booking/cancel/{id}', [SignController::class, 'destroy_myBooking'])->name('myBooking.destroy');

    Route::get('/complate-bookings', [SignController::class, 'complateBookings'])->name('profile.complateBokkings');
    
    Route::get('/complate-Gift', [SignController::class, 'complateGift'])->name('profile.complateGift');

    Route::middleware(['auth'])->prefix('app/affiliate')->name('affiliate.')->group(function () {
        Route::get('/statistics', [AffiliateAdminController::class, 'dashboard'])->name('statistics');
        Route::get('/members', [AffiliateAdminController::class, 'members'])->name('members');
        Route::get('/conversions', [AffiliateAdminController::class, 'conversions'])->name('conversions');
        Route::get('/withdrawals', [AffiliateAdminController::class, 'withdrawals'])->name('withdrawals');
    });
    Route::middleware(['auth'])->group(function () {
        Route::get('/app/gift', [GiftController::class, 'index'])->name('app.gift');
        Route::get('/validate-gift-code', [GiftController::class, 'validateGiftCode']);

        Route::get('/app/invoice', [InvoiceController::class, 'index'])->name('app.invoice');
        Route::get('/app/loyalty', [LoyaltyController::class, 'index'])->name('app.loyalty');
        Route::get('/app/Offerspages', [offersController::class, 'index'])->name('app.offers');
        Route::get('/app/ads/', [AdsController::class, 'index'])->name('app.ads');
        Route::get('/app/reject/', [RejectController::class, 'index'])->name('app.reject');
        Route::get('/app/TermsAndConditions', [TermsAndConditionsController::class, 'index'])->name('app.TermsAndConditions');
        Route::get('/app/Wheel/settings', [WheelController::class, 'index'])->name('app.Wheel');
        Route::get('/app/text', [TextController::class, 'index'])->name('app.text');

        Route::post('/app/Text/store', [TextController::class, 'store'])->name('app.Text');
        Route::post('/app/Wheel/settings/store', [WheelController::class, 'store'])->name('Wheel.store');
        Route::post('/app/reject/store', [RejectController::class, 'store'])->name('app.store');
        Route::post('/app/loyalty/store', [LoyaltyController::class, 'store'])->name('loyalty.store');
        Route::post('/app/ads/store', [AdsController::class, 'store'])->name('ads.store');
        Route::post('/app/TermsAndConditions/store', [TermsAndConditionsController::class, 'store'])->name('TermsAndConditions.store');
        Route::post('/app/Offerspages/store', [offersController::class, 'store'])->name('ouroffersections.store');
        Route::get('/validate-coupon', [InvoiceController::class, 'validateCoupon']);// about serves
        Route::get('/validate-invoice-coupon', [InvoiceController::class, 'validateInvoiceCoupon']);


        Route::put('/TermsAndConditions/{id}/update', [TermsAndConditionsController::class, 'update'])->name('TermsAndConditions.update');
        Route::put('/reject/update/{id}', [RejectController::class, 'update']);
        Route::put('/ads/update-status/{id}', [AdsController::class, 'updateStatus'])->name('ads.update-status');
        Route::put('app/ads/update-link', [AdsController::class, 'update_link'])->name('ads.update_link');

        Route::delete('/app/ads/destroy/{id}', [AdsController::class, 'destroy'])->name('ads.destroy');
        Route::delete('/app/Wheel/settings/destroy/{id}', [WheelController::class, 'destroy'])->name('Wheel.destroy');
        Route::delete('/app/Wheel/settings/destroy_all', [WheelController::class, 'destroy_all'])->name('Wheel.destroy_all');
        Route::get('/TermsAndConditions/{id}', [TermsAndConditionsController::class, 'destroy'])->name('TermsAndConditions.destroy');
        Route::get('/invoices/{id}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
        Route::get('/reject/{id}', [RejectController::class, 'destroy'])->name('reject.destroy');
        Route::get('/gift/delete/{id}', [GiftController::class, 'destroy'])->name('gift.delete');
    });

    //  Get quick cart
    Route::get('/qu/cart', [SaloneBookController::class, 'getUserCart']);
    Route::delete('/qu/cart/remove/{id}', [SaloneBookController::class, 'remove']);

