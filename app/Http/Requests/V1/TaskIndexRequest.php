<?php

namespace App\Http\Requests\V1;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(array_keys(Task::STATUSES))],
            'priority' => ['nullable', Rule::in(array_keys(Task::PRIORITIES))],
            'assignee' => ['nullable', 'integer', 'exists:users,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'due_from' => ['nullable', 'date'],
            'due_to' => ['nullable', 'date', 'after_or_equal:due_from'],
            'overdue' => ['nullable', 'boolean'],
            'trashed' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', Rule::in(['title', 'status', 'priority', 'due_date', 'created_at'])],
            'sort_order' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
