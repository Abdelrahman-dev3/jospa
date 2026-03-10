<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyPoint;
use App\Models\User;
use App\Services\TaqnyatSmsService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingService;
use Modules\Service\Models\Service;

class PhoneAuthController extends Controller
{
    private const OTP_TTL_MINUTES = 5;
    private const REGISTER_DAILY_SMS_LIMIT = 3;
    private const REGISTER_PHONE_SESSION_KEY = 'auth.register.mobile';
    private const REGISTER_USERNAME_SESSION_KEY = 'auth.register.username';
    private const LOGIN_PHONE_SESSION_KEY = 'auth.login.mobile';

    public function showSignupForm(): View|RedirectResponse
    {
        if ($response = $this->redirectIfAuthenticated()) {
            return $response;
        }
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => 'required|string|max:191|unique:users,username',
            'mobile' => 'required|string|max:20|unique:users,mobile',
        ]);

        $phone = $this->normalizePhone($validated['mobile']);

        if (! $phone) {
            return back()->withInput()->with('error', __('messagess.invalid_phone'));
        }

        if ($this->hasReachedRegistrationSmsLimit($phone)) {
            return back()->withInput()->with('error', __('messagess.sms_daily_limit_reached'));
        }

        Session::put([
            self::REGISTER_PHONE_SESSION_KEY => $phone,
            self::REGISTER_USERNAME_SESSION_KEY => $validated['username'],
        ]);

        if (! $this->sendOtp($phone, $this->registerOtpKey($phone))) {
            return back()->withInput()->with('error', __('messagess.error_sending_sms'));
        }

        $this->incrementRegistrationSmsCount($phone);

        return redirect()->route('register.otp.form');
    }

    public function showRegistrationOtpForm(): View|RedirectResponse
    {
        return $this->showOtpVerificationPage(
            mobile: Session::get(self::REGISTER_PHONE_SESSION_KEY),
            fallbackRoute: 'signup',
            submitRoute: 'verify.otp',
            resendRoute: 'register.otp.resend'
        );
    }

    public function verifyRegistrationOtp(Request $request): RedirectResponse
    {
        $otp = $this->validatedOtp($request);
        $phone = Session::get(self::REGISTER_PHONE_SESSION_KEY);

        if ($response = $this->ensureOtpSession($phone, 'signup')) {
            return $response;
        }

        if ($response = $this->ensureOtpMatches($otp, $this->registerOtpKey($phone), 'otp1')) {
            return $response;
        }

        $username = (string) Session::get(
            self::REGISTER_USERNAME_SESSION_KEY,
            $request->input('username', '')
        );

        $this->clearRegistrationState($phone);

        $user = $this->createOrUpdateUser($phone, $username);

        return $this->authenticateAndRedirect($request, $user, __('messagess.mobile_verified_success'));
    }

    public function resendRegistrationOtp(): RedirectResponse
    {
        $phone = Session::get(self::REGISTER_PHONE_SESSION_KEY);

        if (! $phone) {
            return redirect()->route('signup')->with('error', __('messagess.session_expired'));
        }

        if ($this->hasReachedRegistrationSmsLimit($phone)) {
            return back()->with('error', __('messagess.sms_daily_limit_reached'));
        }

        if (! $this->sendOtp($phone, $this->registerOtpKey($phone))) {
            return back()->with('error', __('messagess.error_sending_sms'));
        }

        $this->incrementRegistrationSmsCount($phone);

        return back()->with('success', __('messages.otp_sent'));
    }

    public function showLoginForm(): View|RedirectResponse
    {
        if ($response = $this->redirectIfAuthenticated()) {
            return $response;
        }

        return view('auth.login');
    }

    public function sendLoginOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mobile' => 'required|string|max:20',
        ]);

        $phone = $this->normalizePhone($validated['mobile']);

        if (! $phone) {
            return back()->withInput()->with('error', __('messagess.invalid_phone'));
        }

        $user = User::where('mobile', $phone)->first();

        if (! $user) {
            return back()->withInput()->with('error', __('messages.invalid_credentials'));
        }

        Session::put(self::LOGIN_PHONE_SESSION_KEY, $phone);

        if (! $this->sendOtp($phone, $this->loginOtpKey($phone))) {
            return back()->withInput()->with('error', __('messagess.error_sending_sms'));
        }

        return redirect()->route('login.verify.form')->with('success', __('messages.otp_sent'));
    }

    public function showLoginOtpForm(): View|RedirectResponse
    {
        return $this->showOtpVerificationPage(
            mobile: Session::get(self::LOGIN_PHONE_SESSION_KEY),
            fallbackRoute: 'signin',
            submitRoute: 'verify.send.otp'
        );
    }

    public function verifyLoginOtp(Request $request): RedirectResponse
    {
        $otp = $this->validatedOtp($request);
        $phone = Session::get(self::LOGIN_PHONE_SESSION_KEY);

        if ($response = $this->ensureOtpSession($phone, 'signin')) {
            return $response;
        }

        if ($response = $this->ensureOtpMatches($otp, $this->loginOtpKey($phone), 'otp')) {
            return $response;
        }

        $this->clearLoginState($phone);

        $user = User::where('mobile', $phone)->first();

        if (! $user) {
            return redirect()->route('signin')->with('error', __('messages.invalid_credentials'));
        }

        return $this->authenticateAndRedirect($request, $user, __('messages.login_successfully'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/signin')->with('success', __('messages.user_logout'));
    }

    private function redirectIfAuthenticated(): ?RedirectResponse
    {
        if (Auth::check()) {
            return redirect('/');
        }
        return null;
    }

    private function showOtpVerificationPage(
        ?string $mobile,
        string $fallbackRoute,
        string $submitRoute,
        ?string $resendRoute = null
    ): View|RedirectResponse {
        if ($response = $this->redirectIfAuthenticated()) {
            return $response;
        }

        if (! $mobile) {
            return redirect()->route($fallbackRoute)->with('error', __('messagess.session_expired'));
        }

        return view('auth.otp.verify-phone-otp', [
            'mobile' => $mobile,
            'submitRoute' => $submitRoute,
            'resendRoute' => $resendRoute,
        ]);
    }

    private function ensureOtpSession(?string $phone, string $fallbackRoute): ?RedirectResponse
    {
        if (! $phone) {
            return redirect()->route($fallbackRoute)->with('error', __('messagess.session_expired'));
        }

        return null;
    }

    private function ensureOtpMatches(string $otp, string $cacheKey, string $errorKey): ?RedirectResponse
    {
        $cachedOtp = Cache::get($cacheKey);

        if (! $cachedOtp) {
            return back()->withErrors([
                $errorKey => __('messagess.otp_code_expired'),
            ]);
        }

        if ((string) $cachedOtp !== $otp) {
            return back()->withErrors([
                $errorKey => __('messagess.invalid_otp'),
            ])->withInput();
        }

        return null;
    }

    private function clearRegistrationState(string $phone): void
    {
        Cache::forget($this->registerOtpKey($phone));
        Session::forget([
            self::REGISTER_PHONE_SESSION_KEY,
            self::REGISTER_USERNAME_SESSION_KEY,
        ]);
    }

    private function clearLoginState(string $phone): void
    {
        Cache::forget($this->loginOtpKey($phone));
        Session::forget(self::LOGIN_PHONE_SESSION_KEY);
    }

    private function createOrUpdateUser(string $phone, string $username): User
    {
        $displayName = trim($username) !== '' ? $username : $phone;
        $user = User::firstOrNew(['mobile' => $phone]);

        $user->fill([
            'username' => $user->username ?: $displayName,
            'first_name' => $user->first_name ?: $displayName,
            'last_name' => $user->last_name ?: $displayName,
            'mobile' => $phone,
            'email_verified_at' => now(),
        ]);

        $user->save();

        return $user;
    }

    private function authenticateAndRedirect(Request $request, User $user, string $successMessage): RedirectResponse
    {
        Auth::login($user);
        $request->session()->regenerate();

        return $this->redirectAfterAuthentication($request, $successMessage);
    }

    private function redirectAfterAuthentication(Request $request, string $successMessage): RedirectResponse
    {
        if ($request->session()->has('temp_gift_booking')) {
            return redirect()->route('gift.create');
        }

        if ($request->session()->has('temp_booking')) {
            return $this->completeTempBookingAndRedirect($request);
        }

        $user = $request->user();

        if ($user && ($user->hasRole('admin') || $user->hasRole('employee'))) {
            return redirect('/app')->with('success', $successMessage);
        }

        return redirect('/')->with('success', $successMessage);
    }

    private function completeTempBookingAndRedirect(Request $request): RedirectResponse
    {
        $tempBooking = (array) $request->session()->pull('temp_booking', []);
        $bookingData = (array) ($tempBooking['data'] ?? []);
        $paymentType = (string) ($tempBooking['btn_value'] ?? 'cart');

        $this->completeTempBookings($bookingData, $paymentType);

        if ($paymentType === 'payment') {
            return redirect('/payment?ids=1')->with('success', 'تم تحويل الحجز بنجاح');
        }

        return redirect('/cart')->with('success', 'تم تحويل الحجز بنجاح');
    }

    private function completeTempBookings(array $data, string $paymentType): void
    {
        $user = Auth::user();
        $serviceGroups = $data['services'] ?? [];

        if (! $user || empty($serviceGroups)) {
            return;
        }

        DB::transaction(function () use ($serviceGroups, $data, $paymentType, $user): void {
            foreach ($serviceGroups as $serviceGroup) {
                foreach ($serviceGroup['subServices'] ?? [] as $subService) {
                    $preparedService = $this->prepareTempBookingService($subService);

                    if (! $preparedService) {
                        continue;
                    }

                    $booking = Booking::create(
                        $this->buildBookingPayload($user, $data, $preparedService, $paymentType)
                    );

                    BookingService::create(
                        $this->buildBookingServicePayload($booking, $user, $preparedService)
                    );

                    LoyaltyPoint::firstOrCreate(
                        ['user_id' => $user->id],
                        ['points' => 0]
                    );
                }
            }
        });
    }

    private function prepareTempBookingService(array $subService): ?array
    {
        $serviceId = $subService['id'] ?? null;
        $date = $subService['date'] ?? null;
        $time = $subService['time'] ?? null;
        $staffId = $subService['staffId'] ?? null;

        if (! $serviceId || ! $date || ! $time || ! $staffId) {
            return null;
        }

        $service = Service::find($serviceId);

        if (! $service) {
            return null;
        }

        try {
            $startDateTime = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$time);
        } catch (\Throwable) {
            return null;
        }

        return [
            'service_id' => (int) $serviceId,
            'staff_id' => (int) $staffId,
            'duration' => (int) ($subService['duration'] ?? 0),
            'start_date_time' => $startDateTime,
            'service' => $service,
        ];
    }

    private function buildBookingPayload(
        User $user,
        array $data,
        array $preparedService,
        string $paymentType
    ): array {
        $branchId = (int) ($data['branch'] ?? 1);
        $isInBranch = $branchId !== 0;

        return [
            'note' => $this->buildBookingNote($user, $data, $preparedService['service_id'], $isInBranch),
            'start_date_time' => $preparedService['start_date_time'],
            'user_id' => $user->id,
            'branch_id' => $branchId,
            'created_by' => $user->id,
            'status' => 'pending',
            'location' => $isInBranch ? null : ($data['locationInput'] ?? null),
            'payment_type' => $paymentType,
        ];
    }

    private function buildBookingNote(User $user, array $data, int $serviceId, bool $isInBranch): string
    {
        if ($isInBranch) {
            return 'العميل: '.$user->first_name.'، الجوال: '.$user->mobile.'، الخدمة: '.$serviceId;
        }

        return 'اسم العميل '.($data['customerName'] ?? '')
            .' رقم العميل '.($data['mobileNo'] ?? '')
            .' الحي '.($data['neighborhood'] ?? '');
    }

    private function buildBookingServicePayload(Booking $booking, User $user, array $preparedService): array
    {
        return [
            'booking_id' => $booking->id,
            'service_id' => $preparedService['service_id'],
            'employee_id' => $preparedService['staff_id'],
            'start_date_time' => $preparedService['start_date_time'],
            'service_price' => $preparedService['service']->default_price ?? 0,
            'duration_min' => $preparedService['duration'],
            'sequance' => 1,
            'created_by' => $user->id,
        ];
    }

    private function validatedOtp(Request $request): string
    {
        $validated = $request->validate([
            'otp1' => 'required|digits:1',
            'otp2' => 'required|digits:1',
            'otp3' => 'required|digits:1',
            'otp4' => 'required|digits:1',
        ]);

        return $validated['otp1'].$validated['otp2'].$validated['otp3'].$validated['otp4'];
    }

    private function hasReachedRegistrationSmsLimit(string $phone): bool
    {
        return $this->registrationSmsCount($phone) >= self::REGISTER_DAILY_SMS_LIMIT;
    }

    private function registrationSmsCount(string $phone): int
    {
        return (int) Cache::get($this->registerDailySmsKey($phone), 0);
    }

    private function incrementRegistrationSmsCount(string $phone): void
    {
        Cache::put(
            $this->registerDailySmsKey($phone),
            $this->registrationSmsCount($phone) + 1,
            now()->endOfDay()
        );
    }

    private function sendOtp(string $phone, string $cacheKey): bool
    {
        $otp = (string) random_int(1000, 9999);

        Cache::put($cacheKey, $otp, now()->addMinutes(self::OTP_TTL_MINUTES));

        try {
            app(TaqnyatSmsService::class)->sendSms($phone, __('messagess.otp_sms', ['code' => $otp]));

            return true;
        } catch (\Throwable) {
            Cache::forget($cacheKey);

            return false;
        }
    }

    private function normalizePhone(string $mobile): ?string
    {
        return app(TaqnyatSmsService::class)->validatePhoneNumber($mobile);
    }

    private function registerOtpKey(string $phone): string
    {
        return 'auth:register:otp:'.$phone;
    }

    private function loginOtpKey(string $phone): string
    {
        return 'auth:login:otp:'.$phone;
    }

    private function registerDailySmsKey(string $phone): string
    {
        return 'auth:register:sms-count:'.$phone.':'.now()->toDateString();
    }
}
