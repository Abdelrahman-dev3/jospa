<?php

namespace App\Http\Controllers\Backend;

use App\Authorizable;
use App\Events\Backend\UserCreated;
use App\Events\Backend\UserUpdated;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Permission;
use App\Models\Role;
use App\Notifications\UserAccountCreated;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    // use Authorizable;

    public function __construct()
    {
        // Page Title
        $this->module_title = 'profile.title';

        // module name
        $this->module_name = 'users';

        // directory path of the module
        $this->module_path = 'users';

        // module icon
        $this->module_icon = 'fa-solid fa-users';

        // module model name, path
        $this->module_model = "App\Models\User";

        view()->share([
            'module_title' => $this->module_title,
            'module_icon' => $this->module_icon,
            'module_name' => $this->module_name,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        $user = auth()->user();

        $user->status = 0;

        $user->save();

        event(new UserUpdated($$module_name_singular));

        return response()->json(['message' => 'Account has been deactivated!']);
    }

    /**
     * Resend Email Confirmation Code to User.
     *
     * @param [type] $hashid [description]
     * @return [type] [description]
     */
    public function emailConfirmationResend($id)
    {
        if ($id != auth()->user()->id) {
            if (auth()->user()->hasAnyRole(['admin'])) {
                // Log::info(auth()->user()->name.' ('.auth()->user()->id.') - User Requested for Email Verification.');
            } else {
                // Log::warning(auth()->user()->name.' ('.auth()->user()->id.') - User trying to confirm another users email.');

                abort('404');
            }
        }

        $user = User::where('id', '=', $id)->first();

        if ($user) {
            if ($user->email_verified_at == null) {
                // Send Email To Registered User
                $user->sendEmailVerificationNotification();

                flash('<i class="fas fa-check"></i> Email Sent! Please Check Your Inbox.')->success()->important();

                return redirect()->back();
            } else {
                flash($user->name . ', You already confirmed your email address at ' . $user->email_verified_at->isoFormat('LL'))->success()->important();

                return redirect()->back();
            }
        }
    }

    public function user_list(Request $request)
    {
        $term = trim((string) $request->q);
        $role = $request->role;
        $termDigits = preg_replace('/\D+/', '', $term);

        $query = null;

        if ($role == 'employee') {
            $query = User::role(['manager', 'employee'])
                ->with('media')
                ->where('is_show_calender', 1);
        } elseif ($role == 'user') {
            $query = User::role(['user'])->active();
        }

        if (! $query) {
            return response()->json([]);
        }

        if ($term !== '') {
            $query->where(function ($builder) use ($term, $termDigits) {
                $builder
                    ->where('first_name', 'LIKE', "%{$term}%")
                    ->orWhere('last_name', 'LIKE', "%{$term}%")
                    ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?", ["%{$term}%"]);

                if ($termDigits !== '') {
                    $builder->orWhere('mobile', 'LIKE', "%{$termDigits}%");
                } else {
                    $builder->orWhere('mobile', 'LIKE', "%{$term}%");
                }
            });
        }

        $query->orderByRaw("CASE WHEN mobile IS NULL OR mobile = '' THEN 1 ELSE 0 END")
            ->orderBy('first_name');

        if ($term !== '') {
            $query->limit($role == 'user' ? 200 : 100);
        }

        $query_data = $query->get();

        $data = [];

        foreach ($query_data as $row) {
            $fullName = trim(implode(' ', array_filter([$row->first_name, $row->last_name])));
            $data[] = [
                'id' => $row->id,
                'full_name' => $fullName !== '' ? $fullName : __('messages.unknown'),
                'email' => $row->email,
                'mobile' => $row->mobile,
                'profile_image' => $row->profile_image,
                'created_at' => $row->created_at,
                'first_name' => $row->first_name,
                'last_name' => $row->last_name,
            ];
        }

        return response()->json($data);
    }

    public function create_customer(Request $request)
    {
        $request->validate([
            'first_name' => 'required|min:3|max:191',
            'last_name' => 'nullable|max:191',
            'email' => 'nullable|email|max:191|unique:users,email',
            'mobile' => 'required|string|max:20|unique:users,mobile',
        ], [
            'first_name.required' => 'يرجى إدخال الاسم الأول.',
            'first_name.min' => 'الاسم الأول يجب أن يكون 3 أحرف على الأقل.',
            'last_name.max' => 'اسم العائلة طويل جدًا.',
            'email.email' => 'يرجى إدخال بريد إلكتروني صحيح أو ترك الحقل فارغًا.',
            'email.unique' => 'هذا البريد الإلكتروني مستخدم بالفعل.',
            'mobile.required' => 'يرجى إدخال رقم الجوال.',
            'mobile.unique' => 'رقم الجوال مستخدم بالفعل.',
        ]);

        $data_array = $request->except('_token', 'roles', 'permissions', 'password_confirmation');
        $data_array['last_name'] = trim((string) $request->last_name);
        $data_array['email'] = $request->filled('email') ? $request->email : null;
        $data_array['gender'] = $request->input('gender', 'female');
        $data_array['password'] = Hash::make('123456789');
        $data_array['name'] = trim($request->first_name . ' ' . $data_array['last_name']);

        if ($request->confirmed == 1) {
            $data_array = Arr::add($data_array, 'email_verified_at', Carbon::now());
        } else {
            $data_array = Arr::add($data_array, 'email_verified_at', null);
        }

        $user = User::create($data_array);

        $roles = $request['roles'];
        $permissions = $request['permissions'];

        // Sync Roles
        $roles = ['user'];
        $user->syncRoles($roles);

        \Artisan::call('cache:clear');

        event(new UserCreated($user));

        $message = __('user.user_created');

        if ($request->email_credentials == 1) {
            $data = [
                'password' => '123456789',
            ];

            try {
                $user->notify(new UserAccountCreated($data));
            } catch (\Exception $e) {
                \Log::error($e->getMessage());
            }

            $message = __('user.account_crdential');
        }

        return response()->json(['data' => $user, 'message' => $message, 'status' => true]);
    }



    public function myProfile()
    {
        return view('backend.profile.index');
    }

    public function authData()
    {
        $defaultImage = default_user_avatar();
        return response()->json(['data' => auth()->user(), 'defaultImage' => $defaultImage, 'status' => true]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        $data = User::findOrFail($user->id);
        $request_data = $request->except('profile_image');
        $data->update($request_data);

        if ($request->custom_fields_data) {
            $data->updateCustomFieldData(json_decode($request->custom_fields_data));
        }

        if ($request->hasFile('profile_image')) {
            storeMediaFile($data, $request->file('profile_image'), 'profile_image');
        }
        if ($request->profile_image == null) {
            $data->clearMediaCollection('profile_image');
        }
        if ($user->hasRole('admin')) {
            $message = __('messages.update_form', ['form' => __('profile.admin_profile')]);
        } elseif ($user->hasRole('manager')) {
            $message = __('messages.update_form', ['form' => __('profile.manager_profile')]);
        } elseif ($user->hasRole('employee')) {
            $message = __('messages.update_form', ['form' => __('profile.employee_profile')]);
        } else {
            $message = __('messages.update_form', ['form' => __('profile.default_profile')]);
        }

        return response()->json(['message' => $message, 'status' => true], 200);
    }


    public function change_password(Request $request)
    {
        if (env('IS_DEMO')) {
            return response()->json(['message' => __('messages.permission_denied'), 'status' => false], 200);
        }
        $user = Auth::user(); // Get the currently authenticated user

        $user_id = $user->id; // Retrieve the user's ID

        $data = User::findOrFail($user_id);

        $request_data = $request->only('old_password', 'new_password', 'confirm_password');

        if (!Hash::check($request->old_password, $data->password)) {
            return response()->json(['message' => __('messages.old_password_mismatch'), 'errors' => ['old_password' => __('messages.old_password_mismatch')], 'status' => false], 403);
        }

        if ($request_data['new_password'] === $request_data['old_password']) {
            return response()->json(['message' => __('messages.new_password_mismatch'), 'errors' => ['new_password' => __('messages.new_password_mismatch')], 'status' => false], 403);
        }

        if ($request_data['new_password'] !== $request_data['confirm_password']) {
            return response()->json(['message' => __('messages.password_mismatch'), 'errors' => ['confirm_password' => __('messages.password_mismatch')], 'status' => false], 403);
        }

        $request_data['password'] = Hash::make($request_data['new_password']);

        $data->update($request_data);

        $message = __('messages.password_update');

        return response()->json(['message' => $message, 'status' => true], 200);
    }
}
