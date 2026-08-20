<?php

namespace App\Http\Requests\Api\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:employees,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'designation_id' => ['nullable', 'integer', 'exists:designations,id'],
            'manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'manager')],
            'joining_date' => ['nullable', 'date'],
            'status' => ['required', 'string', 'in:active,inactive'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'is_manager' => ['sometimes', 'boolean'],
        ];
    }
}
