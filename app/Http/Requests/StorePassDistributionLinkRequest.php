<?php

namespace App\Http\Requests;

use App\Models\Pass;
use Illuminate\Foundation\Http\FormRequest;

class StorePassDistributionLinkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $passId = $this->route('pass');
        // Handle both object (from route model binding) and ID (from tests)
        $pass = $passId instanceof Pass ? $passId : Pass::findOrFail($passId);
        return $this->user()->can('createDistributionLink', $pass);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // No input fields needed - link is created automatically
        ];
    }
}


