<?php

namespace App\Http\Controllers\Auth\Trait\API;

use App\Http\Controllers\Auth\Trait\AuthTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\LoginResource;
use App\Http\Resources\RegisterResource;
use App\Http\Resources\SocialLoginResource;
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

    public function register(Request $request)
    {
        return $this->sendRegisterOtp($request);
    }

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

        $user = User::where('mobile', $validated['mobile'])->first();

        if ($user == null || !$user) {
            return response()->json([
                'status' => false,
                'message' => __('messages.register_before_login'),
            ], 200);
        }

        if ($user->is_banned == 1 || $user->status == 0) {
            return response()->json([
                'status' => false,
                'message' => __('messages.login_error'),
            ], 200);
        }

        $user['api_token'] = $user->createToken(setting('app_name'))->plainTextToken;

        $loginResource = new LoginResource($user);
        $message = __('messages.user_login');

        return $this->sendResponse($loginResource, $message);
    }


    // public function socialLogin(Request $request)
    // {
    //     $input = $request->all();

    //     if ($input['login_type'] === 'mobile') {
    //         $user_data = User::where('username', $input['username'])->where('login_type', 'mobile')->first();
    //     } else {
    //         $user_data = User::where('email', $input['email'])->first();
    //     }

    //     if ($user_data != null) {
    //         if (! isset($user_data->login_type) || $user_data->login_type == '') {
    //             if ($request->login_type === 'google') {
    //                 $message = __('validation.unique', ['attribute' => 'email']);
    //             } else {
    //                 $message = __('validation.unique', ['attribute' => 'username']);
    //             }

    //             return $this->sendError($message, 400);
    //         }
    //         $message = __('messages.login_success');
    //     } else {
    //         if ($request->login_type === 'google') {
    //             $key = 'email';
    //             $value = $request->email;
    //         } else {
    //             $key = 'username';
    //             $value = $request->username;
    //         }

    //         $trashed_user_data = User::where($key, $value)->whereNotNull('login_type')->withTrashed()->first();

    //         if ($trashed_user_data != null && $trashed_user_data->trashed()) {
    //             if ($request->login_type === 'google') {
    //                 $message = __('validation.unique', ['attribute' => 'email']);
    //             } else {
    //                 $message = __('validation.unique', ['attribute' => 'username']);
    //             }

    //             return $this->sendError($message, 400);
    //         }

    //         if ($request->login_type === 'mobile' && $user_data == null) {
    //             $otp_response = [
    //                 'status' => true,
    //                 'is_user_exist' => false,
    //             ];

    //             return $this->sendError($otp_response);
    //         }

    //         if ($request->login_type === 'mobile' && $user_data != null) {
    //             $otp_response = [
    //                 'status' => true,
    //                 'is_user_exist' => true,
    //             ];

    //             return $this->sendError($otp_response);
    //         }

    //         $password = ! empty($input['accessToken']) ? $input['accessToken'] : $input['email'];

    //         $input['user_type'] = 'user';
    //         $input['display_name'] = $input['first_name'].' '.$input['last_name'];
    //         $input['password'] = Hash::make($password);
    //         $input['user_type'] = isset($input['user_type']) ? $input['user_type'] : 'user';
            
    //         $user = User::create($input);
    //         $user->assignRole('user');

    //         \Artisan::call('cache:clear');

    //         if (! empty($input['profile_image'])) {
    //             $media = $user->addMediaFromUrl($input['profile_image'])->toMediaCollection('profile_image');
    //             $user->avatar = $media->getUrl();
    //         }
    //         $user_data = User::where('id', $user->id)->first();
    //         $message = trans('messages.save_form', ['form' => $input['user_type']]);
    //     }

        
    //     $user_data['api_token'] = $user_data->createToken('auth_token')->plainTextToken;

    //     $socialLogin = new SocialLoginResource($user_data);

    //     return $this->sendResponse($socialLogin, $message);
    // }

    public function logout(Request $request)
    {
        $user = Auth::guard('sanctum')->user();

        if ($request->is('api*')) {
            $user->save();

            return response()->json(['status' => true, 'message' => __('messages.user_logout')]);
        }
    }

    // public function forgotPassword(Request $request)
    // {
    //     $request->validate([
    //         'email' => 'required|email',
    //     ]);

    //     // We will send the password reset link to this user. Once we have attempted
    //     // to send the link, we will examine the response then see the message we
    //     // need to show to the user. Finally, we'll send out a proper response.
    //     $response = Password::sendResetLink(
    //         $request->only('email')
    //     );
    //     $user = User::where('email', $request->email)->first();
    //     if ($user == null) {
    //         return $response == Password::RESET_LINK_SENT
    //             ? response()->json(['message' => __($response), 'status' => true], 200)
    //             : response()->json(['message' => __($response), 'status' => false], 200);
    //     }

    //     return $response == Password::RESET_LINK_SENT
    //         ? response()->json(['message' => __($response), 'status' => true], 200)
    //         : response()->json(['message' => __($response), 'status' => false], 400);
    // }

    // public function changePassword(Request $request)
    // {
    //     $user = \Auth::user();
    //     $user_id = ! empty($request->id) ? $request->id : $user->id;
    //     $user = User::where('id', $user_id)->first();
    //     if ($user == '') {
    //         return response()->json([
    //             'status' => false,
    //             'message' => __('messages.user_notfound'),
    //         ], 400);
    //     }

    //     $hashedPassword = $user->password;

    //     $match = Hash::check($request->old_password, $hashedPassword);

    //     $same_exits = Hash::check($request->new_password, $hashedPassword);

    //     if ($match) {
    //         if ($same_exits) {
    //             $message = __('messages.old_new_pass_same');

    //             return response()->json([
    //                 'status' => false,
    //                 'message' => __('messages.same_pass'),
    //             ], 400);
    //         }

    //         $user->fill([
    //             'password' => Hash::make($request->new_password),
    //         ])->save();

    //         $success['api_token'] = $user->createToken(setting('app_name'))->plainTextToken;
    //         $success['name'] = $user->name;

    //         return response()->json([
    //             'status' => true,
    //             'data' => $success,
    //             'message' => __('messages.pass_successfull'),
    //         ], 200);
    //     } else {
    //         $success['api_token'] = $user->createToken(setting('app_name'))->plainTextToken;
    //         $success['name'] = $user->name;
    //         $message = __('messages.valid_password');

    //         return response()->json([
    //             'status' => true,
    //             'data' => $success,
    //             'message' => __('messages.pass_successfull'),
    //         ], 200);
    //     }
    // }

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
    
    // public function signup(Request $request)
    // {
    //     $user = $this->registerCustomer($request);
    //     $user['api_token'] = $user->createToken(setting('app_name'))->plainTextToken;
    
    //     return response()->json([
    //         'status' => true,
    //         'message' => __('messages.register_successfull'),
    //         'data' => new RegisterResource($user),
    //     ], 200);
    // }

    public function sendRegisterOtp(Request $request)
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:191', 'unique:users,username'],
            'mobile' => ['required', 'string', 'max:20', 'unique:users,mobile'],
        ]);
    
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

    // private function registerCustomer(Request $request): User
    // {
    //     $validated = $request->validate([
    //         'username' => ['required', 'string', 'max:191', 'unique:users,username'],
    //         'mobile' => ['required', 'string', 'max:20', 'unique:users,mobile'],
    //     ]);
    
    //     $user = User::create([
    //         'first_name' => $validated['username'],
    //         'last_name' => '',
    //         'username' => $validated['username'],
    //         'mobile' => $validated['mobile'],
    //         'status' => 1,
    //     ]);
    
    //     $user->assignRole('user');
    
    //     return $user;
    // }

}
