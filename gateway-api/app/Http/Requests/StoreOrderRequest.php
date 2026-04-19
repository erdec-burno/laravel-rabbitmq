<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_email' => ['required', 'email'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
        ];
    }

    /**
     * Get the validation error messages that apply to the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_email.required' => 'Customer email is required.',
            'customer_email.email' => 'Customer email must be a valid email address.',
            'amount.required' => 'Order amount is required.',
            'amount.numeric' => 'Order amount must be numeric.',
            'amount.min' => 'Order amount must be greater than zero.',
            'currency.required' => 'Currency is required.',
            'currency.size' => 'Currency must be a 3-letter ISO code.',
            'currency.alpha' => 'Currency must contain letters only.',
        ];
    }
}
