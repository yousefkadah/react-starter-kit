<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PassTypeSampleRequest extends FormRequest
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
        $passTypes = [
            'generic',
            'coupon',
            'boardingPass',
            'eventTicket',
            'storeCard',
            'offer',
            'loyalty',
            'transit',
            'stampCard',
        ];

        $imageSlots = ['icon', 'logo', 'strip', 'thumbnail', 'background', 'footer'];

        $imageSlotRules = [];
        foreach ($imageSlots as $slot) {
            $imageSlotRules["images.$slot"] = ['required', 'string'];
        }

        return array_merge([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'pass_type' => ['required', 'string', 'in:'.implode(',', $passTypes)],
            'platform' => ['nullable', 'string', 'in:apple,google'],
            'fields' => ['required', 'array'],
            'images' => ['required', 'array'],
        ], $imageSlotRules);
    }
}
