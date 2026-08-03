<?php
    use App\Http\Controllers\Backend\{
        AdsController,BlogPostController,ContactMessageController,GiftController,InvoiceController,PaymentAttemptController,
        LoyaltyController,ModuleController,offersController,ReportsController,
        RejectController,TermsAndConditionsController,TextController,
        BackendController,BackupController,BranchController,NotificationsController,
        SettingController,UserController,UsersController
    };

    use App\Http\Controllers\{
        BookingsController,BookingCartController,GiftCardController,PackageBookingController,
        BookingController,LanguageController,FrontendLoyaltyController,
        PaymentController,PermissionController,RoleController,RolePermission,
        SearchController,PackageDetailsController,ProfileController,SystemUtilityController,
        PrivacyPolicyController
    };

    use App\Services\Payment\Strategies\{
        TamaraPaymentStrategy,CardPaymentStrategy
    };

    use Illuminate\Support\Facades\Route;


    use Modules\Employee\Http\Controllers\Backend\EmployeesController;
    use App\Http\Controllers\Auth\PhoneAuthController;

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


    Route::controller(PhoneAuthController::class)->middleware('guest')->group(function () {
        Route::get('/signup', 'showSignupForm')->name('signup');
        Route::post('/signup', 'register')->name('signup.store');

        Route::get('/signin', 'showLoginForm')->name('signin');
        Route::post('/signin/verify', 'sendLoginOtp')->name('signin.verify');
        Route::get('send-OTP', 'showLoginOtpForm')->name('login.verify.form');
        Route::post('verify-send-otp', 'verifyLoginOtp')->name('verify.send.otp');

        Route::get('verify-mobile', 'showRegistrationOtpForm')->name('register.otp.form');
        Route::post('verify-otp', 'verifyRegistrationOtp')->name('verify.otp');
        Route::post('verify-mobile/resend', 'resendRegistrationOtp')->name('register.otp.resend');
    });

    Route::controller(BookingsController::class)->group(function () {
        Route::get('/salonService', 'salon')->name('salon.create');
        Route::get('/HomeService', 'home')->name('home.create');
    });

    Route::controller(GiftCardController::class)->group(function () {
        Route::get('/giffte', 'index')->name('gift.page');
        Route::get('/gift-cards', 'store')->name('gift.create');
    });

//    Route::controller(PrivacyPolicyController::class)->group(function () {
//        Route::get('/privacy-policy', 'index')->name('privacy.policy');
//    });

    Route::controller(PackageDetailsController::class)->group(function () {
        Route::get('/details/{id}', 'show')->name('home.details');
    });


    Route::controller(PaymentController::class)->group(function () {
        Route::post('/payment-chanal', 'payment')->name('payment-chanal');
        Route::get('tabby/success/{invoice}', 'tabbySuccess')->name('tabby.success');
        Route::get('tabby/fail/{invoice}', 'tabbyFail')->name('tabby.fail');
        Route::get('tabby/cancel/{invoice}', 'tabbyCancel')->name('tabby.cancel');
        Route::match(['get', 'post'], '/urpay/success', 'urpaySuccess')->name('urpay.success');
        Route::match(['get', 'post'], '/urpay/failure', 'urpayFail')->name('urpay.failure');
        Route::match(['get', 'post'], '/urpay/cancel', 'urpayCancel')->name('urpay.cancel');

        Route::get('/tamara/success', [TamaraPaymentStrategy::class, 'success'])->name('tamara.success');
        Route::get('/tamara/failure', [TamaraPaymentStrategy::class, 'failure'])->name('tamara.failure');
        Route::get('/tamara/cancel', [TamaraPaymentStrategy::class, 'cancel'])->name('tamara.cancel');
    });
    Route::get('/payment/hyperpay/checkout', [CardPaymentStrategy::class, 'checkout'])->name('hyperpay.checkout');
    Route::get('/payment/callback', [CardPaymentStrategy::class, 'callback'])->name('hyperpay.callback');
    Route::match(['get', 'post'], '/payment/hyperpay/result', [CardPaymentStrategy::class, 'callbackPlain'])->name('hyperpay.callback.plain');


    Route::controller(EmployeesController::class)->group(function () {
        Route::post('/staff/working-hours/{id}', 'store_working_houer')->name('staff.working-hours.store');
    });

    Route::controller(FrontendLoyaltyController::class)->group(function () {
        Route::get('/loyalety', 'loyalety')->name('home.loyalety');
    });

    Route::controller(BookingCartController::class)->group(function () {
        // Use when user not loggin
        Route::post('/cart', 'store')->name('cart.store');
        Route::get('/cart/add/{id}', 'addToCart')->name('cart.add');
    });

    Route::middleware('auth')->group(function () {

        Route::controller(BookingCartController::class)->group(function () {
            Route::get('/cart', 'index')->name('cart.page');
            Route::delete('/cart/{id}', 'destroy')->name('cart.destroy');
            Route::delete('p/cart/{id}', 'destroy_product')->name('p.cart.destroy');
            Route::delete('g/cart/{id}', 'destroy_gift')->name('g.cart.destroy');
            Route::delete('/cart/destroy/All', 'destroy_All')->name('cart.destroyAll');
            Route::get('/success-py-invoice', 'handlePaymentResult');
            Route::get('/loyalty-points/check', 'checkLoyaltyPoints')->name('loyalty.check');
            Route::post('/cart-pay', 'cartPay')->name('cart.payment');
        });

        Route::controller(ContactMessageController::class)->group(function () {
            Route::get('/admin/contact-messages', 'index')->name('contact.index');
            Route::post('/admin/contact-messages/{id}/reply', 'reply')->name('admin.contact-messages.reply');
            Route::post('/admin/contact-messages/bulk-action', 'bulkAction')->name('admin.contact-messages.bulk-action');
            Route::post('/contact', 'store')->name('contact.store');
        });

        Route::controller(ProfileController::class)->group(function () {
            Route::get('/profile', 'profile')->name('profile');
            Route::put('/profile/{id}/update', 'update')->name('profile.update');
        });

        Route::controller(PhoneAuthController::class)->group(function () {
            Route::post('/logout', 'logout')->name('logout');
        });
    });

    Route::controller(PackageBookingController::class)->group(function () {
        Route::get('all/branchs/', 'allbranchs');
        Route::post('/package-booking', 'storePackageBooking')->name('package.booking.store');
        Route::get('/complete-package-booking', 'completePackageBooking')->name('package.booking.complete');
    });

    Route::controller(BookingController::class)->group(function () {
        Route::post('/bookings', 'store')->name('bookings.store');
    });

    Route::controller(SystemUtilityController::class)->group(function () {
        Route::get('lang/{locale}', 'switchLocale');
        Route::get('/clear-config', 'clearConfig');
        Route::get('/clear-cache', 'clearCache');
        Route::get('/clear-route', 'clearRoute');
        Route::get('/modules-list', 'modulesList');
        Route::get('/clear-view', 'clearView');
        Route::get('/clear-all', 'clearAll');
        Route::get('storage-link', 'storageLink');
    });



    // Auth Routes
    require __DIR__.'/auth.php';

    Route::controller(SystemUtilityController::class)->middleware('auth')->group(function () {
        Route::get('/admin', 'adminRedirect');
    });

    Route::group(['middleware' => ['auth']], function () {
        Route::controller(NotificationsController::class)->group(function () {
            Route::get('notification-list', 'notificationList')->name('notification.list');
            Route::get('notification-counts', 'notificationCounts')->name('notification.counts');
        });
    });

    // Language Switch
    Route::controller(LanguageController::class)->group(function () {
        Route::get('language/{language}', 'switch')->name('language.switch');
    });
    Route::group(['prefix' => 'app', 'middleware' => 'auth'], function () {
        Route::controller(BackendController::class)->group(function () {
            Route::post('set-user-setting', 'setUserSetting')->name('backend.setUserSetting');
        });

        Route::group(['as' => 'backend.', 'middleware' => ['auth']], function () {
            Route::controller(SearchController::class)->group(function () {
                Route::get('get_search_data', 'get_search_data')->name('get_search_data');
            });

            // Sync Role & Permission
            Route::controller(RolePermission::class)->group(function () {
                Route::get('/permission-role', 'index')->name('permission-role.list')->middleware('password.confirm');
                Route::post('/permission-role/store/{role_id}', 'store')->name('permission-role.store');
                Route::get('/permission-role/reset/{role_id}', 'reset_permission')->name('permission-role.reset');
            });
            // Role & Permissions Crud
            Route::resource('permission', PermissionController::class);
            Route::resource('role', RoleController::class);

            Route::group(['prefix' => 'module', 'as' => 'module.'], function () {
                Route::controller(ModuleController::class)->group(function () {
                    Route::get('index_data', 'index_data')->name('index_data');
                    Route::post('update-status/{id}', 'update_status')->name('update_status');
                });
            });

            Route::resource('module', ModuleController::class);

            /*
            *
            *  Settings Routes
            *
            * ---------------------------------------------------------------------
            */
            Route::group(['middleware' => []], function () {
                Route::controller(SettingController::class)->group(function () {
                    Route::get('settings/{vue_capture?}', 'index')->name('settings')->where('vue_capture', '^(?!storage).*$');
                    Route::get('settings-data', 'index_data');
                    Route::post('settings', 'store')->name('settings.store');
                    Route::post('setting-update', 'update')->name('setting.update');
                    Route::get('clear-cache', 'clear_cache')->name('clear-cache');
                    Route::post('verify-email', 'verify_email')->name('verify-email');
                });
            });

            /*
            *
            *  Notification Routes
            *
            * ---------------------------------------------------------------------
            */
            Route::group(['prefix' => 'notifications', 'as' => 'notifications.'], function () {
                Route::controller(NotificationsController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/markAllAsRead', 'markAllAsRead')->name('markAllAsRead');
                    Route::delete('/deleteAll', 'deleteAll')->name('deleteAll');
                    Route::get('/{id}', 'show')->name('show');
                });
            });

            /*
            *
            *  Backup Routes
            *
            * ---------------------------------------------------------------------
            */
            Route::group(['prefix' => 'backups', 'as' => 'backups.'], function () {
                Route::controller(BackupController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/create', 'create')->name('create');
                    Route::get('/download/{file_name}', 'download')->name('download');
                    Route::get('/delete/{file_name}', 'delete')->name('delete');
                });
            });

            Route::controller(ReportsController::class)->group(function () {
                Route::get('sales-by-date', 'sales_by_date_report')->name('reports.sales-by-date');
                Route::get('sales-by-date-index-data', 'sales_by_date_report_index_data')->name('reports.sales-by-date.index_data');
                Route::get('sales-by-date-export', 'sales_by_date_report_export')->name('reports.sales-by-date.export');
                Route::get('daily-booking-report', 'daily_booking_report')->name('reports.daily-booking-report');
                Route::get('daily-booking-report-index-data', 'daily_booking_report_index_data')->name('reports.daily-booking-report.index_data');
                Route::get('overall-booking-report', 'overall_booking_report')->name('reports.overall-booking-report');
                Route::get('overall-booking-report-index-data', 'overall_booking_report_index_data')->name('reports.overall-booking-report.index_data');
                Route::get('payout-report', 'payout_report')->name('reports.payout-report');
                Route::get('payout-report-index-data', 'payout_report_index_data')->name('reports.payout-report.index_data');
                Route::get('staff-report', 'staff_report')->name('reports.staff-report');
                Route::get('staff-report-index-data', 'staff_report_index_data')->name('reports.staff-report.index_data');
                Route::get('order-report', 'order_report')->name('reports.order-report');
                Route::get('order-report-index-data', 'order_report_index_data')->name('reports.order-report.index_data');
                Route::get('financial-report', 'financial_report')->name('reports.financial-report');
                Route::get('financial-report-index-data', 'financial_report_index_data')->name('reports.financial-report.index_data');
                Route::get('coupon-report', 'coupon_report')->name('reports.coupon-report');
                Route::get('coupon-report-index-data', 'coupon_report_index_data')->name('reports.coupon-report.index_data');
                Route::get('promotion-report', 'promotion_report')->name('reports.promotion-report');
                Route::get('promotion-report-index-data', 'promotion_report_index_data')->name('reports.promotion-report.index_data');
                Route::get('payment-transactions-report', 'payment_transactions_report')->name('reports.payment-transactions-report');
                Route::get('payment-transactions-report-index-data', 'payment_transactions_report_index_data')->name('reports.payment-transactions-report.index_data');

                // Review Routes
                Route::get('daily-booking-report-review', 'daily_booking_report_review')->name('reports.daily-booking-report-review');
                Route::get('overall-booking-report-review', 'overall_booking_report_review')->name('reports.overall-booking-report-review');
                Route::get('payout-report-review', 'payout_report_review')->name('reports.payout-report-review');
                Route::get('staff-report-review', 'staff_report_review')->name('reports.staff-report-review');
                Route::get('order_booking_report_review', 'order_booking_report_review')->name('reports.order_booking_report_review');
            });

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
                Route::controller(BackendController::class)->group(function () {
                    Route::get('/', 'index')->name('home');
                    Route::post('set-current-branch/{branch_id}', 'setCurrentBranch')->name('set-current-branch');
                    Route::post('reset-branch', 'resetBranch')->name('reset-branch');
                });

                Route::group(['prefix' => ''], function () {
                    Route::controller(BackendController::class)->group(function () {
                        Route::get('dashboard', 'index')->name('dashboard');
                    });

                    /**
                     * Branch Routes
                     */
                    Route::group(['prefix' => 'branch', 'as' => 'branch.'], function () {
                        Route::controller(BranchController::class)->group(function () {
                            Route::get('index_list', 'index_list')->name('index_list');
                            Route::get('assign/{id}', 'assign_list')->name('assign_list');
                            Route::post('assign/{id}', 'assign_update')->name('assign_update');
                            Route::get('index_data', 'index_data')->name('index_data');
                            Route::get('trashed', 'trashed')->name('trashed');
                            Route::patch('trashed/{id}', 'restore')->name('restore');
                            // Branch Gallery Images
                            Route::get('gallery-images/{id}', 'getGalleryImages');
                            Route::post('gallery-images/{id}', 'uploadGalleryImages');
                            Route::post('bulk-action', 'bulk_action')->name('bulk_action');
                            Route::post('update-status/{id}', 'update_status')->name('update_status');
                            Route::post('update-select-value/{id}/{action_type}', 'update_select')->name('update_select');
                            Route::post('branch-setting', 'UpdateBranchSetting')->name('branch_setting');
                        });
                    });
                    Route::controller(BranchController::class)->group(function () {
                        Route::get('branch-info', 'branchData')->name('branchData');
                    });
                    Route::resource('branch', BranchController::class);

                    /*
                    *
                    *  Users Routes
                    *
                    * ---------------------------------------------------------------------
                    */
                    Route::group(['prefix' => 'users', 'as' => 'users.'], function () {
                        Route::controller(UserController::class)->group(function () {
                            Route::get('user-list', 'user_list')->name('user_list');
                            Route::get('emailConfirmationResend/{id}', 'emailConfirmationResend')->name('emailConfirmationResend');
                            Route::post('create-customer', 'create_customer')->name('create_customer');
                            Route::post('information', 'update')->name('information');
                            Route::post('change-password', 'change_password')->name('change_password');
                        });

                        Route::controller(UsersController::class)->group(function () {
                            Route::get('create', 'create')->name('create')->middleware('permission:view_role_permissions');
                            Route::post('/', 'store')->name('store')->middleware('permission:view_role_permissions');
                        });
                    });
                });
                Route::controller(UserController::class)->group(function () {
                    Route::get('my-profile/{vue_capture?}', 'myProfile')->name('my-profile')->where('vue_capture', '^(?!storage).*$');
                    Route::get('my-info', 'authData')->name('authData');
                    Route::post('my-profile/change-password', 'change_password')->name('change_password');
                });
            });
        });
    });

    Route::controller(ProfileController::class)->group(function () {
        Route::get('/my-bookings', 'myBookings')->name('profile.my_bookings');
        Route::get('/coupon', 'coupon')->name('profile.coupon');
        Route::post('/booking/cancel/{id}', 'destroy_myBooking')->name('myBooking.destroy');
        Route::get('/complate-bookings', 'complateBookings')->name('profile.complateBokkings');
        Route::get('/complate-Gift', 'complateGift')->name('profile.complateGift');
    });

    Route::middleware(['auth'])->group(function () {
        Route::controller(GiftController::class)->group(function () {
            Route::get('/app/gift', 'index')->name('app.gift');
            Route::match(['get', 'post'], '/validate-gift-code', 'validateGiftCode');
            Route::get('/gift/delete/{id}', 'destroy')->name('gift.delete');
        });

        Route::controller(InvoiceController::class)->group(function () {
            Route::get('/app/invoice', 'index')->name('app.invoice');
            Route::get('/invoices/{id}', 'destroy')->name('invoices.destroy');
        });

        Route::controller(PaymentAttemptController::class)->group(function () {
            Route::get('/app/payment-attempts', 'index')->name('backend.payment-attempts.index');
        });

        Route::controller(LoyaltyController::class)->group(function () {
            Route::get('/app/loyalty', 'index')->name('app.loyalty');
            Route::post('/app/loyalty/store', 'store')->name('loyalty.store');
        });

        Route::controller(offersController::class)->group(function () {
            Route::get('/app/Offerspages', 'index')->name('app.offers');
            Route::post('/app/Offerspages/store', 'store')->name('ouroffersections.store');
        });

        Route::controller(AdsController::class)->group(function () {
            Route::get('/app/ads/', 'index')->name('app.ads');
            Route::post('/app/ads/store', 'store')->name('ads.store');
            Route::put('/ads/update-status/{id}', 'updateStatus')->name('ads.update-status');
            Route::put('app/ads/update-link', 'update_link')->name('ads.update_link');
            Route::delete('/app/ads/destroy/{id}', 'destroy')->name('ads.destroy');
        });

        Route::controller(BlogPostController::class)->group(function () {
            Route::get('/app/blog', 'index')->name('app.blog.index');
            Route::get('/app/blog/create', 'create')->name('app.blog.create');
            Route::post('/app/blog', 'store')->name('app.blog.store');
            Route::get('/app/blog/{blogPost}/edit', 'edit')->name('app.blog.edit');
            Route::put('/app/blog/{blogPost}', 'update')->name('app.blog.update');
            Route::delete('/app/blog/{blogPost}', 'destroy')->name('app.blog.destroy');
        });

        Route::controller(RejectController::class)->group(function () {
            Route::get('/app/reject/', 'index')->name('app.reject');
            Route::post('/app/reject/store', 'store')->name('app.store');
            Route::put('/reject/update/{id}', 'update');
            Route::get('/reject/{id}', 'destroy')->name('reject.destroy');
        });

        Route::controller(TermsAndConditionsController::class)->group(function () {
            Route::get('/app/TermsAndConditions', 'index')->name('app.TermsAndConditions');
            Route::post('/app/TermsAndConditions/store', 'store')->name('TermsAndConditions.store');
            Route::put('/TermsAndConditions/{id}/update', 'update')->name('TermsAndConditions.update');
            Route::get('/TermsAndConditions/{id}', 'destroy')->name('TermsAndConditions.destroy');
        });

        Route::controller(TextController::class)->group(function () {
            Route::get('/app/text', 'index')->name('app.text');
            Route::post('/app/Text/store', 'store')->name('app.Text');
        });
    });

    //  Get quick cart
    Route::controller(PackageDetailsController::class)->group(function () {
        Route::get('/qu/cart', 'getUserCart');
        Route::delete('/qu/cart/remove/{id}', 'remove');
    });
