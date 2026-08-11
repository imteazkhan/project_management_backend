<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users',
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/'
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()      // Uppercase & lowercase
                    ->letters()         // At least one letter
                    ->numbers()         // At least one number
                    ->symbols()         // At least one symbol
                    ->uncompromised(),  // Check against breached passwords
            ],
            'phone' => ['nullable', 'string', 'regex:/^[0-9]{10,15}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.regex' => 'Please enter a valid email address',
            'password.uncompromised' => 'This password appears in data breaches. Please choose a different password.',
            'password.mixed' => 'Password must contain both uppercase and lowercase letters',
            'password.symbols' => 'Password must contain at least one special character',
        ];
    }
}
