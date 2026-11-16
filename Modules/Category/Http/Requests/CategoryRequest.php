<?php

namespace Modules\Category\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CategoryRequest extends FormRequest
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
                    'slug' => 'string',
                    'name' => 'required|string',
                    'calendar_color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
                    'booking_start_time' => ['nullable', 'date_format:H:i', 'required_with:booking_end_time'],
                    'booking_end_time' => ['nullable', 'date_format:H:i', 'required_with:booking_start_time', 'after:booking_start_time'],
                ];
                break;
            case 'put':
            case 'patch':
                return [
                    'slug' => 'string',
                    'name' => 'required|string',
                    'calendar_color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
                    'booking_start_time' => ['nullable', 'date_format:H:i', 'required_with:booking_end_time'],
                    'booking_end_time' => ['nullable', 'date_format:H:i', 'required_with:booking_start_time', 'after:booking_start_time'],
                ];
                break;
        }

        return [];
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

    protected function failedValidation(Validator $validator)
    {
        $data = [
            'status' => false,
            'message' => $validator->errors()->first(),
            'all_message' => $validator->errors(),
        ];

        if (request()->wantsJson() || request()->is('api/*')) {
            throw new HttpResponseException(response()->json($data, 422));
        }

        throw new HttpResponseException(redirect()->back()->withInput()->with('errors', $validator->errors()));
    }
}
