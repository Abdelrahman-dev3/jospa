<?php

namespace Modules\Employee\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SystemUserRequest extends FormRequest
{
    public function rules()
    {
        $systemUserId = $this->route('id') ?? $this->id;
        $mobileRules = [
            'required',
            'string',
            function ($attribute, $value, $fail) use ($systemUserId) {
                $normalizedMobile = User::normalizeMobile((string) $value);

                if (! $normalizedMobile) {
                    $fail(__('messagess.invalid_phone'));

                    return;
                }

                $mobileExists = User::query()
                    ->whereNull('deleted_at')
                    ->whereMobileMatches($normalizedMobile)
                    ->when($systemUserId, fn ($query) => $query->where('id', '!=', $systemUserId))
                    ->exists();

                if ($mobileExists) {
                    $fail(__('users.mobile_already_exists'));
                }
            },
        ];

        switch (strtolower($this->getMethod())) {
            case 'post':
                return [
                    'first_name' => 'required|string|max:255',
                    'last_name' => 'required|string|max:255',
                    'email' => 'required|string|email|max:255|unique:users,email',
                    'mobile' => $mobileRules,
                    'password' => 'required|string|min:8',
                    'confirm_password' => 'required|same:password',
                ];
            case 'put':
            case 'patch':
                return [
                    'first_name' => 'required|string|max:255',
                    'last_name' => 'required|string|max:255',
                    'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($systemUserId)->whereNull('deleted_at')],
                    'mobile' => $mobileRules,
                    'password' => 'nullable|string|min:8',
                    'confirm_password' => 'nullable|required_with:password|same:password',
                ];
            default:
                return [];
        }
    }

    protected function prepareForValidation(): void
    {
        $mobile = User::normalizeMobile((string) $this->input('mobile', ''));

        if ($mobile) {
            $this->merge(['mobile' => $mobile]);
        }
    }

    public function authorize()
    {
        return true;
    }
}
