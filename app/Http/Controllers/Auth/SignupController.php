<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TaqnyatSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

class SignupController extends Controller
{
    public function index()
    {
        return view('auth.register');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:191|unique:users,username',
            'mobile' => 'required|string|max:20|unique:users,mobile',
        ]);

        $smsService = new TaqnyatSmsService();
        $phone = $smsService->validatePhoneNumber($validated['mobile']);

        if (!$phone) {
            return redirect()->back()->with('error', __('messagess.invalid_phone'));
        }

        Session::put('mobile', $phone);
        Session::put('username', $validated['username']);

        $smsCountKey = 'sms_count_' . $phone . '_' . date('Y-m-d');
        $sentCount = Cache::get($smsCountKey, 0);

        if ($sentCount >= 3) {
            return redirect()->back()->with('error', __('messagess.sms_daily_limit_reached'));
        }

        $otp = rand(1000, 9999);
        Cache::put('otp_' . $phone, $otp, now()->addMinutes(5));

        $message = __('messagess.otp_sms', ['code' => $otp]);

        try {
            $smsService->sendSms($phone, $message);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('messagess.error_sending_sms'));
        }

        Cache::put($smsCountKey, $sentCount + 1, now()->endOfDay());

        return redirect()->route('verify.mobile', ['mobile' => $phone]);
    }
}
