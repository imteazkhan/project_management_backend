<?php

namespace App\Http\Requests\Api\Project;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'status' => ['required', 'string', 'in:active,completed,on_hold,archived'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'client' => ['nullable', 'string', 'max:255'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'github_link' => ['nullable', 'url', 'max:255'],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')],
        ];
    }
}
