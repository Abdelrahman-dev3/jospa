<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingService;
use App\Services\TaqnyatSmsService;

class SignController extends Controller
{
    public function login()
    {
        return view('auth.login');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'mobile' => 'required|string|max:20',
        ]);

        $smsService = new TaqnyatSmsService();
        $phone = $smsService->validatePhoneNumber($request->mobile);

        if (!$phone) {
            return back()->with('error', __('messagess.invalid_phone'));
        }

        $user = \App\Models\User::where('mobile', $phone)->first();

        if (!$user) {
            return back()->with('error', __('messages.invalid_credentials'));
        }

        $otp = rand(1000, 9999);
        Cache::put('login_otp_' . $phone, $otp, now()->addMinutes(5));
        Session::put('login_mobile', $phone);

        $message = __('messagess.otp_sms', ['code' => $otp]);

        try {
            $smsService->sendSms($phone, $message);
        } catch (\Exception $e) {
            return back()->with('error', __('messagess.error_sending_sms'));
        }

        return redirect()->route('login.verify.form')->with('success', 'تم إرسال كود التحقق إلى موبايلك');
    }

    public function showVerifyForm()
    {
        if (auth()->check()) {
            return redirect('/');
        }

        return view('verify.OTPlogin');
    }

    public function verifyOTP(Request $request)
    {
        $request->validate([
            'otp1' => 'required|digits:1',
            'otp2' => 'required|digits:1',
            'otp3' => 'required|digits:1',
            'otp4' => 'required|digits:1',
        ]);

        $otpInput = $request->otp1 . $request->otp2 . $request->otp3 . $request->otp4;
        $phone = Session::get('login_mobile');

        if (!$phone) {
            return redirect()->route('login')->with('error', __('messages.session_expired'));
        }

        $cachedOtp = Cache::get('login_otp_' . $phone);

        if (!$cachedOtp || $cachedOtp != $otpInput) {
            return back()->withErrors([
                'otp' => __('messages.invalid_otp'),
            ])->withInput();
        }

        Cache::forget('login_otp_' . $phone);

        $user = \App\Models\User::where('mobile', $phone)->first();
        Auth::login($user);
        $request->session()->regenerate();

        if ($request->session()->has('temp_gift_booking')) {
            return redirect()->route('gift.create');
        }

        if ($request->session()->has('temp_booking')) {
            $temp = $request->session()->get('temp_booking');
            $data = $temp['data'];
            $btn_value = $temp['btn_value'];
            $this->complateTempBookings($data, $btn_value);
            session()->forget('temp_booking');

            if ($btn_value == 'cart') {
                return redirect()->to('/cart')->with('success', 'تم تحويل الحجز بنجاح');
            } elseif ($btn_value == 'payment') {
                return redirect()->to('/payment?ids=1')->with('success', 'تم تحويل الحجز بنجاح');
            }
        }

        if (auth()->user()->hasRole('admin') || auth()->user()->hasRole('employee')) {
            return redirect()->to('/app')->with('success', __('messages.login_successfully'));
        }

        return redirect()->to('/')->with('success', __('messages.login_successfully'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/signin')->with('success', 'تم تسجيل الخروج بنجاح');
    }

    private function complateTempBookings($data, $btn_value)
    {
        $user = auth()->user();

        if (!empty($data['services'])) {
            foreach ($data['services'] as $service) {
                if (!empty($service['subServices'])) {
                    foreach ($service['subServices'] as $sub) {
                        $subId = $sub['id'];
                        $date = $sub['date'];
                        $time = $sub['time'];
                        $duration = $sub['duration'];
                        $staffId = $sub['staffId'];
                        $startDateTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $time);

                        $booking = new Booking();
                        $booking->note = 'العميل: ' . $user->first_name . '، الجوال: ' . $user->mobile . '، الخدمة: ' . $subId;
                        $booking->start_date_time = $startDateTime;
                        $booking->user_id = $user->id;
                        $booking->branch_id = $data['branch'] ?? 1;
                        $booking->created_by = $user->id;
                        $booking->status = 'pending';
                        $booking->location = null;
                        $booking->payment_type = $btn_value;
                        $booking->save();

                        $bookingService = new BookingService();
                        $bookingService->booking_id = $booking->id;
                        $bookingService->service_id = $subId;
                        $bookingService->employee_id = $staffId;
                        $bookingService->start_date_time = $startDateTime;
                        $bookingService->service_price = \Modules\Service\Models\Service::find($subId)->default_price ?? 0;
                        $bookingService->duration_min = $duration;
                        $bookingService->sequance = 1;
                        $bookingService->created_by = $user->id;
                        $bookingService->save();

                        \App\Models\LoyaltyPoint::firstOrCreate(
                            ['user_id' => $user->id],
                            ['points' => 0]
                        );
                    }
                }
            }
        }
    }
}
