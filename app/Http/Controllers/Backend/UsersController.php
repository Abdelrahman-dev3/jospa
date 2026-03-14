<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\UserAccountCreated;
use Carbon\Carbon;
use Illuminate\Http\Request;

class UsersController extends Controller
{
    public function __construct()
    {
        $this->module_title = 'Users';
        $this->module_name = 'users';
        $this->module_path = 'users';
        $this->module_icon = 'fa-solid fa-users';
        $this->module_model = "App\Models\User";
    }

    public function create()
    {
        $module_title = $this->module_title;
        $module_name = $this->module_name;
        $module_path = $this->module_path;
        $module_icon = $this->module_icon;
        $module_model = $this->module_model;
        $module_name_singular = \Str::singular($module_name);

        $module_action = 'Create';

        $roles = Role::with('permissions')->get();
        $permissions = Permission::select('name', 'id')->get();

        return view(
            "backend.$module_path.create",
            compact('module_title', 'module_name', 'module_icon', 'module_action', 'roles', 'permissions', 'module_path', 'module_name_singular')
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|min:2|max:191',
            'last_name' => 'required|min:2|max:191',
            'email' => 'required|email|max:191|unique:users',
            'mobile' => 'required|string|max:20|unique:users,mobile',
        ], [
            'first_name.required' => __('users.first_name_required'),
            'last_name.required' => __('users.last_name_required'),
            'email.required' => __('users.email_required'),
            'email.email' => __('users.email_invalid'),
            'email.unique' => __('users.email_already_exists'),
            'mobile.required' => __('users.mobile_required'),
            'mobile.unique' => __('users.mobile_already_exists'),
        ]);

        $data = $request->only([
            'first_name',
            'last_name',
            'email',
            'mobile',
            'status',
        ]);

        $data['status'] = $request->has('status') ? 1 : 0;
        $data['email_verified_at'] = $request->has('confirmed') ? Carbon::now() : null;
        $user = User::create($data);

        $roles = $request->input('roles', ['user']);
        $permissions = $request->input('permissions', []);

        if ($roles) {
            $user->syncRoles($roles);
        }

        if ($permissions) {
            $user->syncPermissions($permissions);
        }

        \Artisan::call('cache:clear');

        $flashData = [
            'flash_success' => __('users.user_created'),
        ];

        if ($request->has('email_credentials')) {
            try {
                $user->notifyNow(new UserAccountCreated([
                    'login_url' => route('signin'),
                ]));

                $flashData['flash_success'] .= ' ' . __('users.account_crdential');
            } catch (\Throwable $e) {
                \Log::error($e->getMessage());
                $flashData['flash_warning'] = __('users.account_crdential_failed');
            }
        }

        return redirect()->route('backend.users.create')->with($flashData);
    }
}
