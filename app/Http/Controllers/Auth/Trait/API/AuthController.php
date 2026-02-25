<?php

namespace App\Http\Controllers\Auth\Trait\API;

use App\Http\Controllers\Auth\Trait\AuthTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\LoginResource;
use App\Http\Resources\RegisterResource;
use App\Http\Resources\SocialLoginResource;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Services\TaqnyatSmsService;
use Auth;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    use AuthTrait;
    /**
     * Login api
     *
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'mobile' => ['required', 'string', 'max:20'],
        ]);

        $smsService = new TaqnyatSmsService();
        $phone = $smsService->validatePhoneNumber($validated['mobile']);

        if (! $phone) {
            return $this->sendError(__('messagess.invalid_phone'), [], 422);
        }

        $user = User::where('mobile', $phone)->first();

        if ($user == null || !$user) {
            return $this->sendError(__('messages.register_before_login'));
        }

        if ($user->is_banned == 1 || $user->status == 0) {
            return $this->sendError(__('messages.login_error'));
        }

        $dailyKey = 'login_otp_count_'.$phone.'_'.date('Y-m-d');
        $dailyCount = (int) Cache::get($dailyKey, 0);

        if ($dailyCount >= 711) {
            return $this->sendError(__('messagess.sms_daily_limit_reached'), [], 429);
        }

        $otp = 1111;//(string) random_int(1000, 9999);

        Cache::put('login_otp_'.$phone, [
            'otp' => $otp,
            'user_id' => $user->id,
        ], now()->addMinutes(5));
        Cache::put($dailyKey, $dailyCount + 1, now()->endOfDay());

        if ((int) setting('is_taqnyat_sms') === 1) {
            $message = __('messagess.otp_sms', ['code' => $otp]);
            // $sent = $smsService->sendSms($phone, $message);

            // if ($sent === false) {
            //     return $this->sendError(__('messagess.sms_failed'), [], 500);
            // }
        }

        return $this->sendResponse([
            'mobile' => $phone,
            'expires_in' => 300,
        ], __('messages.otp_sent'));
    }

    public function resendLoginOtp(Request $request)
    {
        return $this->sendLoginOtp($request);
    }

    public function verifyLoginOtp(Request $request)
    {
        $validated = $request->validate([
            'mobile' => ['required', 'string', 'max:20'],
            'otp' => ['required', 'digits:4'],
        ]);

        $smsService = new TaqnyatSmsService();
        $phone = $smsService->validatePhoneNumber($validated['mobile']);

        if (! $phone) {
            return $this->sendError(__('messagess.invalid_phone'), [], 422);
        }

        $cached = Cache::get('login_otp_'.$phone);

        if (! $cached || ! isset($cached['otp'], $cached['user_id'])) {
            return $this->sendError(__('messagess.session_expired'), [], 422);
        }

        $attemptKey = 'login_otp_attempts_'.$phone;
        $attempts = (int) Cache::get($attemptKey, 0);

        if ($attempts >= 5) {
            Cache::forget('login_otp_'.$phone);
            Cache::forget($attemptKey);

            return $this->sendError(__('auth.throttle', ['seconds' => 300, 'minutes' => 5]), [], 429);
        }

        if ((string) $cached['otp'] !== (string) $validated['otp']) {
            Cache::put($attemptKey, $attempts + 1, now()->addMinutes(5));

            return $this->sendError(__('messagess.invalid_otp'), [], 422);
        }

        $user = User::where('id', $cached['user_id'])->where('mobile', $phone)->first();

        if (! $user) {
            Cache::forget('login_otp_'.$phone);
            Cache::forget($attemptKey);

            return $this->sendError(__('messages.user_notfound'), [], 404);
        }

        if ($user->is_banned == 1 || $user->status == 0) {
            Cache::forget('login_otp_'.$phone);
            Cache::forget($attemptKey);

            return $this->sendError(__('messages.login_error'));
        }

        Cache::forget('login_otp_'.$phone);
        Cache::forget($attemptKey);
        $user['api_token'] = $user->createToken(setting('app_name'))->plainTextToken;

        return $this->sendResponse(new LoginResource($user), __('messages.user_login'));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => __('messages.user_logout')
        ]);
    }
    public function sendRegisterOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'max:191', 'unique:users,username'],
            'mobile' => ['required', 'string', 'max:20', 'unique:users,mobile'],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                $validator->errors()->first(),
                $validator->errors(),
                422
            );
        }

        $validated = $validator->validated();
        $smsService = new TaqnyatSmsService();
        $phone = $smsService->validatePhoneNumber($validated['mobile']);
    
        if (! $phone) {
            return $this->sendError(__('messagess.invalid_phone'), [], 422);
        }
    
        $dailyKey = 'register_otp_count_'.$phone.'_'.date('Y-m-d');
        $dailyCount = (int) Cache::get($dailyKey, 0);
    
        if ($dailyCount >= 3) {
            return $this->sendError(__('messagess.sms_daily_limit_reached'), [], 429);
        }
    
        $otp = (string) 1111;//random_int(1000, 9999);
    
        Cache::put('register_otp_'.$phone, [
            'username' => $validated['username'],
            'otp' => $otp,
        ], now()->addMinutes(5));
        Cache::put($dailyKey, $dailyCount + 1, now()->endOfDay());
    
        $message = __('messagess.otp_sms', ['code' => $otp]);
        // $sent = $smsService->sendSms($phone, $message);
    
        // if ($sent === false) {
        //     return $this->sendError(__('messagess.sms_failed'), [], 500);
        // }
    
        return $this->sendResponse([
            'mobile' => $phone,
            'expires_in' => 300,
        ], __('messages.otp_sent'));
    }

    public function resendRegisterOtp(Request $request)
    {
        return $this->sendRegisterOtp($request);
    }

    public function verifyRegisterOtp(Request $request)
    {
        $validated = $request->validate([
            'mobile' => ['required', 'string', 'max:20'],
            'otp' => ['required', 'digits:4'],
        ]);
    
        $smsService = new TaqnyatSmsService();
        $phone = $smsService->validatePhoneNumber($validated['mobile']);
    
        if (! $phone) {
            return $this->sendError(__('messagess.invalid_phone'), [], 422);
        }
    
        $cached = Cache::get('register_otp_'.$phone);
    
        if (! $cached || ! isset($cached['otp'], $cached['username'])) {
            return $this->sendError(__('messagess.session_expired'), [], 422);
        }
    
        $attemptKey = 'register_otp_attempts_'.$phone;
        $attempts = (int) Cache::get($attemptKey, 0);
    
        if ($attempts >= 5) {
            Cache::forget('register_otp_'.$phone);
            Cache::forget($attemptKey);
    
            return $this->sendError(__('auth.throttle', ['seconds' => 300, 'minutes' => 5]), [], 429);
        }
    
        if ((string) $cached['otp'] !== (string) $validated['otp']) {
            Cache::put($attemptKey, $attempts + 1, now()->addMinutes(5));
    
            return $this->sendError('Invalid OTP code.', [], 422);
        }
    
        Cache::forget('register_otp_'.$phone);
        Cache::forget($attemptKey);
    
        if (User::where('mobile', $phone)->exists() || User::where('username', $cached['username'])->exists()) {
            return $this->sendError(__('validation.unique', ['attribute' => 'mobile/username']), [], 422);
        }
    
        $user = User::create([
            'first_name' => $cached['username'],
            'last_name' => '',
            'username' => $cached['username'],
            'mobile' => $phone,
            'email_verified_at' => now(),
            'status' => 1,
        ]);
    
        $user->assignRole('user');
        $user['api_token'] = $user->createToken(setting('app_name'))->plainTextToken;
    
        return $this->sendResponse(new RegisterResource($user), __('messages.register_successfull'));
    }

    
    public function updateProfile(Request $request)
    {
        $user = \Auth::user();
        if ($request->has('id') && ! empty($request->id)) {
            $user = User::where('id', $request->id)->first();
        }
        if ($user == null) {
            return response()->json([
                'message' => __('messages.no_record'),
            ], 400);
        }
        $user->fill($request->all())->update();

        $user_data = User::find($user->id);
        if ($request->has('profile_image')) {
            $request->file('profile_image');

            storeMediaFile($user_data, $request->file('profile_image'), 'profile_image');
        }

        $user_data->save();

        $message = __('messages.profile_update');
        $user_data['user_role'] = $user->getRoleNames();
        $user_data['profile_image'] = $user->profile_image;
        unset($user_data['roles']);
        unset($user_data['media']);

        return response()->json([
            'status' => true,
            'data' => $user_data,
            'message' => $message,
        ], 200);
    }

    public function userDetails(Request $request)
    {
        $userID = $request->id;
        $user = User::find($userID);
        if (! $user) {
            return response()->json(['status' => false, 'message' => __('messages.user_notfound')], 404);
        }

        return response()->json(['status' => true, 'data' => $user, 'message' => __('messages.user_details_successfull')]);
    }

    public function deleteAccount(Request $request)
    {
        $user_id = \Auth::user()->id;
        $user = User::where('id', $user_id)->first();
        if ($user == null) {
            $message = __('messages.user_not_found');

            return response()->json([
                'status' => false,
                'message' => $message,
            ], 200);
        }
        $user->booking()->forceDelete();
        $user->forceDelete();
        $message = __('messages.delete_account');

        return response()->json([
            'status' => true,
            'message' => $message,
        ], 200);
    }
}
