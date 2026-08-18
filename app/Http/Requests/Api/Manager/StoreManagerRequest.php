<?php

namespace App\Http\Requests\Api\Manager;

use Illuminate\Foundation\Http\FormRequest;

class StoreManagerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id', 'unique:managers,user_id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ];
    }
}
