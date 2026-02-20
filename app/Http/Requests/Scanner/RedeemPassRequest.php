<?php

namespace App\Http\Requests\Scanner;

use Illuminate\Foundation\Http\FormRequest;

class RedeemPassRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pass_id' => ['required', 'integer'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pass_id.required' => 'A pass ID is required for redemption.',
            'pass_id.integer' => 'The pass ID must be a valid number.',
        ];
    }
}
