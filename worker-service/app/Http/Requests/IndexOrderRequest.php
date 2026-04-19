<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexOrderRequest extends FormRequest
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
            'status' => ['nullable', 'string', 'in:'.implode(',', [
                Order::STATUS_RECEIVED,
                Order::STATUS_PROCESSED,
                Order::STATUS_FAILED,
            ])],
            'customer_email' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
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
            'status.in' => 'Status must be one of: received, processed, failed.',
            'customer_email.max' => 'Customer email filter may not be greater than 255 characters.',
            'date_from.date' => 'Date from must be a valid date.',
            'date_to.date' => 'Date to must be a valid date.',
            'date_to.after_or_equal' => 'Date to must be greater than or equal to date from.',
        ];
    }
}
