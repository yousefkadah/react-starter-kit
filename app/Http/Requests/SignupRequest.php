<?php

namespace App\Http\Requests;

use App\Services\EmailDomainService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SignupRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->symbols()],
            'region' => ['required', 'in:EU,US'],
            'industry' => ['nullable', 'string', 'max:255'],
            'agree_terms' => ['required', 'accepted'],
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'This email address is already registered.',
            'password.confirmed' => 'Passwords do not match.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.letters' => 'Password must contain at least one letter.',
            'password.numbers' => 'Password must contain at least one number.',
            'password.symbols' => 'Password must contain at least one symbol (!@#$%^&*).',
            'agree_terms.accepted' => 'You must accept the terms and conditions.',
            'region.required' => 'Please select a data region (EU or US).',
            'region.in' => 'Region must be either EU or US.',
        ];
    }

    /**
     * Determine approval status based on email domain.
     * Called during authorization before validation.
     */
    public function getApprovalStatus(): string
    {
        $service = app(EmailDomainService::class);

        return $service->getApprovalStatus($this->email);
    }

    /**
     * Get the email domain being used.
     */
    public function getDomain(): string
    {
        $service = app(EmailDomainService::class);

        return $service->extractDomain($this->email);
    }
}
