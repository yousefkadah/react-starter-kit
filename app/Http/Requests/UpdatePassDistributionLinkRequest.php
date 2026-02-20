<?php

namespace App\Http\Requests;

use App\Models\Pass;
use App\Models\PassDistributionLink;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePassDistributionLinkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $passId = $this->route('pass');
        $linkId = $this->route('distributionLink');

        // Handle both object (from route model binding) and ID (from tests)
        $pass = $passId instanceof Pass ? $passId : Pass::findOrFail($passId);
        $link = is_int($linkId) || is_string($linkId) ? PassDistributionLink::findOrFail($linkId) : $linkId;

        return $this->user()->can('updateDistributionLink', [$pass, $link]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => 'required|in:active,disabled',
        ];
    }
}
