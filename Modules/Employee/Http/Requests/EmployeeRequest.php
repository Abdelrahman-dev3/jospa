<?php

namespace Modules\Employee\Http\Requests;

use App\Models\User;
use App\Support\SaudiPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $employeeId = $this->route('employee') ?? $this->route('id') ?? $this->id;
        $mobileRules = [
            'required',
            'string',
            function ($attribute, $value, $fail) {
                if (! SaudiPhoneNumber::normalize((string) $value)) {
                    $fail(__('messagess.invalid_phone'));
                }
            },
            Rule::unique('users', 'mobile')->ignore($employeeId)->whereNull('deleted_at'),
        ];

        switch (strtolower($this->getMethod())) {
            case 'post':
                return [
                    'first_name' => 'required|string|max:255',
                    'last_name' => 'required|string|max:255',
                    'email' => 'required|string|unique:users,email',
                    'mobile' => $mobileRules,
                    'employee_login_otp' => 'nullable|digits:4',
                    'password' => 'required|min:8',
                    'confirm_password' => 'required|same:password',
                ];
                break;
            case 'put':
            case 'patch':
                return [
                    'first_name' => 'required|string|max:255',
                    'last_name' => 'required|string|max:255',
                    'email' => ['required', 'string', Rule::unique('users', 'email')->ignore($this->id)->whereNull('deleted_at')],
                    'mobile' => $mobileRules,
                    'employee_login_otp' => 'nullable|digits:4',
                ];
                break;
            default:
                return [];
                break;
        }
    }

    protected function prepareForValidation(): void
    {
        $mobile = User::normalizeSaudiMobile((string) $this->input('mobile', ''));
        $employeeLoginOtp = preg_replace('/\D+/', '', (string) $this->input('employee_login_otp', ''));

        if ($mobile) {
            $this->merge(['mobile' => $mobile]);
        }

        $this->merge([
            'employee_login_otp' => $employeeLoginOtp !== '' ? $employeeLoginOtp : null,
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }
}
