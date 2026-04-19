<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexProcessedOrderMessageRequest extends FormRequest
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
            'message_type' => ['nullable', 'string', 'max:255'],
            'message_id' => ['nullable', 'string', 'max:255'],
            'external_order_id' => ['nullable', 'string', 'max:255'],
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
            'message_type.max' => 'Message type filter may not be greater than 255 characters.',
            'message_id.max' => 'Message id filter may not be greater than 255 characters.',
            'external_order_id.max' => 'External order id filter may not be greater than 255 characters.',
        ];
    }
}
