<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
  use App\Models\User;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;

class MobileVerificationController extends Controller
{
    public function showVerifyForm(Request $request)
    {
        if (auth()->check()) {
            return redirect('/');
        }
        $mobile = $request->mobile;
        return view('verify.mobile', compact('mobile'));
    }


    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp1' => 'required|digits:1',
            'otp2' => 'required|digits:1',
            'otp3' => 'required|digits:1',
            'otp4' => 'required|digits:1',
        ]);
        
        $otpInput = $request->otp1 . $request->otp2 . $request->otp3 . $request->otp4; // 6579
        
        $mobile = Session::get('mobile');
        
        if (!$mobile) {
            return redirect()->route('login')->withErrors(['otp' => 'Session expired']);
        }
        
        $cacheOtp = Cache::get('otp_'.$mobile);
        
        if (!$cacheOtp) {
            return back()->withErrors([
                'otp1' => 'Verification code expired, please resend the code.'
            ]);
        }

        
        $username = Session::get('username');

    if ($otpInput == $cacheOtp) {
        
        Cache::forget('otp_'.$mobile);
        Session::forget(['mobile', 'username']);

        $user = User::where('mobile', $mobile)->first();

        if (!$user) {
            $user = User::create([
                'username' => $username,
                'first_name' => $username,
                'last_name' => $username,
                'mobile' => $mobile,
                'email_verified_at' => now(),
            ]);
        } else {
            $user->update([
                'email_verified_at' => now(),
            ]);
        }

        auth()->login($user);
        return redirect()->route('frontend.home')->with('success', 'Mobile verified successfully!');
    }

    return back()->withErrors(['otp1' => 'The OTP is incorrect.'])->withInput();
}

    
    public function resendOtp(Request $request)
    {
        $mobile = $request->input('mobile');
        if (!$mobile) {
            return redirect()->route('login')->withErrors(['otp' => __('messagess.session_expired')]);
        }
    
        $cacheKey = "otp_resend_count_{$mobile}";
        $resendCount = Cache::get($cacheKey, 0);
    
        if ($resendCount >= 3) {
            return back()->withErrors([
                'otp' => __('messagess.otp_resend_limit')
            ]);
        }
    
        Cache::put($cacheKey, $resendCount + 1, now()->addMinutes(5));
    
        $otp = rand(1000, 9999);
        Cache::put('otp_'.$mobile, $otp, now()->addMinutes(5));
    
        $message = __('messagess.otp_sms', ['code' => $otp]);
    
        try {
            (new TaqnyatSmsService())->sendSms($mobile, $message);
        } catch (\Throwable $e) {
            return back()->withErrors([
                'otp' => __('messagess.sms_failed')
            ]);
        }
    
        return back()->with('success', __('messagess.otp_resent'));
    }


}
