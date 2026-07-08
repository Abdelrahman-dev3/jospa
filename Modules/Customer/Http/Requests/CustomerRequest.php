<?php

namespace Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        switch (strtolower($this->getMethod())) {
            case 'post':
                return [
                    'first_name' => 'required|string|max:255',
                    'last_name' => 'nullable|string|max:255',
                    'email' => 'nullable|email|max:255|unique:users,email',
                    'mobile' => 'required|string|max:20',
                    'gender' => 'nullable|in:male,female',
                ];
                break;
            case 'put':
            case 'patch':
                return [
                    'first_name' => 'required|string|max:255',
                    'last_name' => 'nullable|string|max:255',
                    'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->id)->whereNull('deleted_at')],
                    'mobile' => 'required|string|max:20',
                    'gender' => 'nullable|in:male,female',
                ];
                break;
            default:
                return [];
                break;
        }
    }

    public function messages()
    {
        return [
            'first_name.required' => 'يرجى إدخال الاسم الأول.',
            'first_name.max' => 'الاسم الأول يجب ألا يزيد عن 255 حرفًا.',
            'last_name.max' => 'اسم العائلة يجب ألا يزيد عن 255 حرفًا.',
            'email.email' => 'يرجى إدخال بريد إلكتروني صحيح أو ترك الحقل فارغًا.',
            'email.max' => 'البريد الإلكتروني طويل جدًا.',
            'email.unique' => 'هذا البريد الإلكتروني مستخدم بالفعل.',
            'mobile.required' => 'يرجى إدخال رقم الجوال.',
            'mobile.max' => 'رقم الجوال يجب ألا يزيد عن 20 رقمًا.',
            'gender.in' => 'القيمة المختارة للجنس غير صحيحة.',
        ];
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
