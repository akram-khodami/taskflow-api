<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in(['admin', 'manager', 'member'])],
            'exclude_self' => ['nullable', 'boolean'],
            'only_managers' => ['nullable', 'boolean'],
            'exclude_admins' => ['nullable','in:true,false,1,0'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'not_in_project' => ['nullable', 'integer', 'exists:projects,id'],
            'sort_by' => ['nullable', Rule::in(['id', 'name', 'email', 'role', 'created_at'])],
            'sort_order' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
