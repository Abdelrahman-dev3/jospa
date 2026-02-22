<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use App\Services\TaqnyatSmsService;
use Modules\Wallet\Models\Wallet;
use Modules\Booking\Models\Booking;
use App\Models\LoyaltyPoint;
use App\Models\reject;
use Modules\Promotion\Models\Coupon;
use Modules\Booking\Models\BookingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use App\Models\GiftCard;

class SignController extends Controller
{
    public function index()
    {
        return view("auth.register" );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:191|unique:users,username',
            'mobile'   => 'required|string|max:20|unique:users,mobile',
        ]);
        $smsService = new TaqnyatSmsService();
        $phone = $smsService->validatePhoneNumber($validated["mobile"]);
    
        if (!$phone) {
            return redirect()->back()->with('error', __('messagess.invalid_phone'));
        }

        Session::put('mobile', $phone);
        Session::put('username', $validated['username']);
        
        $smsCountKey = 'sms_count_'.$phone.'_'.date('Y-m-d');
        $sentCount = Cache::get($smsCountKey, 0);

        if ($sentCount >= 3) {
            return redirect()->back()->with('error',  __('messagess.sms_daily_limit_reached'));
        }

        $otp = rand(1000, 9999);

        Cache::put('otp_'.$phone, $otp, now()->addMinutes(5));
        
        $message = __('messagess.otp_sms', ['code' => $otp]);

        try {
            $smsService->sendSms($phone, $message);
        } catch (\Exception $e) {
            return redirect()->back()->with('error',__('messagess.error_sending_sms'));
        }
    
        Cache::put($smsCountKey, $sentCount + 1, now()->endOfDay());
        return redirect()->route('verify.mobile', ['mobile' => $phone]);
    
    }

    public function login()
    {
        return view("auth.login" );
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
    
        Cache::put('login_otp_'.$phone, $otp, now()->addMinutes(5));
    
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
    
        $cachedOtp = Cache::get('login_otp_'.$phone);
    
        if (!$cachedOtp || $cachedOtp != $otpInput) {
            return back()->withErrors([
                'otp' => __('messages.invalid_otp'),
            ])->withInput();
        }
    
        Cache::forget('login_otp_'.$phone);
    
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
            $this->complateTempBookings($data,$btn_value);
            session()->forget('temp_booking');
            if ($btn_value == 'cart') {
                return redirect()->to('/cart')->with('success', 'تم تحويل الحجز بنجاح');
            } elseif ($btn_value == 'payment') {
                return redirect()->to('/payment?ids=1')->with('success', 'تم تحويل الحجز بنجاح');
            }
        }

        if (auth()->user()->hasRole('admin') || auth()->user()->hasRole('employee')) {
            return redirect()->to('/app')->with('success', __('messages.login_successfully'));
        }else{
            return redirect()->to('/')->with('success', __('messages.login_successfully'));
        }
    }
    
    public function profile()
    {
        $user = auth()->user();

        $baseQuery = Booking::with('service.service', 'service.employee')->whereHas('service')->where('created_by', $user->id)->whereNull('deleted_by');

        $pending = (clone $baseQuery)->whereNotIn('status', ['completed', 'canceled'])->whereNull('deleted_by')->count();
        
        $completed = (clone $baseQuery)->where('payment_status', 1)->where('status', 'completed')->count();
        
        $completedGift = GiftCard::where('user_id', $user->id)->count();

        $coupons = Coupon::with('promotion')->where('is_expired', 0)->where('use_limit', '>=', 1)->count();


        $wallet = Wallet::where('user_id', $user->id)->first();
        $balance = $wallet ? $wallet->amount : 0.00;

        $points = LoyaltyPoint::where('user_id', $user->id)->value('points') ?? 0;


        $bookings = Booking::with('service.service','service.employee')
            ->where('created_by', $user->id)
            ->whereHas('services')
            ->whereNull('deleted_by')
            ->get();
            
        return view('frontend.account.profile', compact('user', 'balance', 'points' , 'bookings' , 'pending' , 'completed' , 'coupons', 'completedGift'));
    }
    public function update(Request $request, $id)
    {
        $request->validate([
            'first_name'     => 'required|string|max:255',
            'last_name'      => 'required|string|max:255',
            'mobile'         => 'required|string|max:20|unique:users,mobile,' . $id,
            'email'          => 'required|email|max:255|unique:users,email,' . $id,
            'address'        => 'nullable|string|max:255',
            'city'           => 'nullable|string|max:255',
            'country'        => 'nullable|string|max:255',
            'date_of_birth'  => 'nullable|date|before:today',
            'profile_image'  => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $data = [];

        if ($request->hasFile('profile_image')) {
            $image = $request->file('profile_image');
            $imageName = 'user_' . $id . '_' . time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('profile_images'), $imageName);
            $data['avatar'] = 'profile_images/' . $imageName;
        } else {
            $data['avatar'] = auth()->user()->avatar;
        }


        $data['first_name']    = $request->first_name;
        $data['last_name']     = $request->last_name;
        $data['email']         = $request->email;
        $data['mobile']        = $request->mobile;
        $data['date_of_birth'] = $request->date_of_birth;
        $data['address']       = $request->address;
        $data['city']          = $request->city;
        $data['country']       = $request->country;

        User::where('id', $id)->update($data);

        return redirect()->back()->with('success', __('messages.profile_updated'));
    }

    public function coupon()
    {

    $coupons = Coupon::with('promotion')->where('is_expired', 0)->where('use_limit', '>=', 1)->get();

    return view('frontend.account.coupons', compact('coupons'));
    }

    public function myBookings()
    {
    $reasons = reject::all();

    $bookings = Booking::with('service.service','service.employee')->where('created_by', auth()->user()->id)->whereNull('deleted_by')->where('status', '!=', 'completed')->get();

    $today = now()->toDateString();

    $gifts = GiftCard::where('user_id', auth()->id())->whereDate('created_at', '>=', $today)->where('payment_status', 1 )->get();
    
    return view('frontend.account.bookings.index', compact('bookings','reasons','gifts'));
    }

    public function destroy_myBooking(Request $request,$id)
    {
        $user = auth()->user();

        $booking = Booking::find($id);

        $booking->delete();

        $reasons = $request->input('reasons', []);

        foreach ($reasons as $reasonId) {
            $reason = Reject::find($reasonId);
            if ($reason) {
                $reason->increment('count');
            }
        }

        return response()->json(['success' => true, 'message' => __('messagess.item_removed_from_cart')]);
    }

    public function complateBookings()
    {

    $bookings = Booking::with('service.service' ,'service.employee')->where('created_by', auth()->user()->id)->whereNull('deleted_by')->where('payment_status', 1)->where('status', '=', 'completed')->get();

    return view('frontend.account.bookings.completed', compact('bookings'));
    }

    public function complateGift()
    {

    $gifts = GiftCard::where('user_id', auth()->id())->get();

    return view('frontend.account.gifts.completed', compact('gifts'));
    }

    public function logout(Request $request) // done
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/signin')->with('success', 'تم تسجيل الخروج بنجاح');
}

    public function apiStore(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'mobile' => 'required|string|unique:users,mobile',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'mobile' => $validated['mobile'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'gender' => 'female',
            'status' => 1,
        ]);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user
        ], 201);
    }
    private function complateTempBookings($data,$btn_value){
        $user = auth()->user();
        if (!empty($data['services'])) {
            foreach ($data['services'] as $service) {
                if (!empty($service['subServices'])) {
                    foreach ($service['subServices'] as $sub) {
                        $subId = $sub['id'];
                        $date = $sub['date'];
                        $time = $sub['time'];
                        $duration = $sub['duration'];
                        $price = $sub['price'];
                        $staffId = $sub['staffId'];
                        $startDateTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $time);

                        $booking = new Booking();
                        $booking->note = 'العميل: ' . $user->first_name . '، الجوال: ' .  $user->mobile . '، الخدمة: ' . $subId;
                        $booking->start_date_time = $startDateTime;
                        $booking->user_id         = $user->id;
                        $booking->branch_id       = $data['branch'] ?? 1;
                        $booking->created_by      = $user->id;
                        $booking->status          = 'pending';
                        $booking->location       =  null;
                        $booking->payment_type       =  $btn_value;
                        $booking->save();

                        //  الحجز التاني
                        $bookingService = new BookingService();
                        $bookingService->booking_id       = $booking->id;
                        $bookingService->service_id       = $subId;
                        $bookingService->employee_id      = $staffId;
                        $bookingService->start_date_time  = $startDateTime;
                        $bookingService->service_price    = \Modules\Service\Models\Service::find($subId)->default_price ?? 0;
                        $bookingService->duration_min     = $duration;
                        $bookingService->sequance         = 1;
                        $bookingService->created_by      = $user->id;
                        $bookingService->save();

                        $loyalty = \App\Models\LoyaltyPoint::firstOrCreate(
                            ['user_id' => $user->id],
                            ['points' => 0]
                        );
                    }
                }
            }
        }
    }
}

