<?php

namespace App\Http\Requests\Api\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'manager')],
            'members' => ['nullable', 'array'],
            'members.*.user_id' => ['required_with:members', 'integer', 'exists:users,id'],
            'members.*.role' => ['required_with:members', 'string', 'in:team_leader,manager,employee'],
        ];
    }
}
