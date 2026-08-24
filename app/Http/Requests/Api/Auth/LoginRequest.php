<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Password is excluded from the global TrimStrings middleware, so stray
        // leading/trailing whitespace picked up when copy-pasting a temporary
        // password out of an email would otherwise cause login to fail.
        if ($this->has('password') && is_string($this->password)) {
            $this->merge(['password' => trim($this->password)]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string'], // Track device
        ];
    }
}
