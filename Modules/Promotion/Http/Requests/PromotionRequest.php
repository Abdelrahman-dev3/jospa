<?php

namespace Modules\Promotion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromotionRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        if ($this->has('services')) {
            $services = $this->input('services');

            // If it's a JSON string, decode it
            if (is_string($services)) {
                $decoded = json_decode($services, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $this->merge(['services' => $decoded]);
                } else {
                    $this->merge(['services' => []]);
                }
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'services' => ['required'],
            'start_date_time' => ['required', 'date'],
            'end_date_time' => ['required', 'date', 'after:start_date_time'],
            'discount_type' => ['required', 'in:fixed,percent'],
            'use_limit' => ['required', 'integer', 'min:1'],
            'status' => ['sometimes', 'boolean'],
        ];

        // Add discount validation based on type
        if ($this->input('discount_type') === 'fixed') {
            $rules['discount_amount'] = ['required', 'numeric', 'min:0.01'];
        } elseif ($this->input('discount_type') === 'percent') {
            $rules['discount_percentage'] = ['required', 'numeric', 'min:0.01', 'max:100'];
        }

        // Conditionally add unique rule for coupon_code based on coupon_type
        if ($this->input('coupon_type') === 'custom') {
            $rules['coupon_code'] = [
                'required',
                'string',
                'max:255',
                Rule::unique('promotions_coupon', 'coupon_code')->ignore($this->route('promotion')),
            ];
        } elseif ($this->input('coupon_type') === 'bulk') {
            $rules['number_of_coupon'] = ['required', 'integer', 'min:1', 'max:1000'];
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'services.required' => 'At least one service must be selected.',
            'services.array' => 'Services must be an array.',
            'services.min' => 'At least one service must be selected.',
            'coupon_code.unique' => 'Coupon code must be unique.',
            'use_limit.min' => 'Use limit must be greater than or equal to 1',
            'discount_amount.required' => 'Discount amount is required when type is fixed.',
            'discount_percentage.required' => 'Discount percentage is required when type is percent.',
            'discount_percentage.max' => 'Discount percentage cannot exceed 100%.',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
