<?php

namespace App\Http\Requests\Api\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'password' => [
                'nullable',
                Password::min(8)->mixedCase()->letters()->numbers()->symbols()->uncompromised(),
            ],
            'role' => ['required', 'string', 'in:admin,manager,employee'],
            'employee_id' => [
                'nullable',
                'integer',
                'exists:employees,id',
                Rule::unique('users', 'employee_id')->ignore($this->route('user')),
            ],
        ];
    }
}
