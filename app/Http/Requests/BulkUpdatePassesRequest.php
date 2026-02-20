<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdatePassesRequest extends FormRequest
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
        $userId = $this->user()?->id;

        return [
            'pass_template_id' => [
                'required',
                'integer',
                Rule::exists('pass_templates', 'id')->where(static function ($query) use ($userId): void {
                    $query->where('user_id', $userId);
                }),
            ],
            'field_key' => ['required', 'string'],
            'field_value' => ['required'],
            'filters' => ['nullable', 'array'],
            'filters.status' => ['nullable', 'in:active'],
            'filters.platform' => ['nullable', 'in:apple,google'],
        ];
    }
}
