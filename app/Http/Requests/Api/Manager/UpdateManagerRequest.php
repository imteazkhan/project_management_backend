<?php

namespace App\Http\Requests\Api\Manager;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateManagerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::unique('managers', 'user_id')->ignore($this->route('manager')),
            ],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ];
    }
}
