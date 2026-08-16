<?php

namespace App\Http\Requests\V1;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    //todo:use enum instead of constant if you had time
    public function authorize(): bool
    {
        $project = $this->route('project');

        // Check if user can create tasks in this project
        return $this->user()->can('create', [Task::class, $project]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(array_keys(Task::STATUSES))],
            'priority' => ['nullable', Rule::in(array_keys(Task::PRIORITIES))],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'assignee_id' => ['nullable', 'exists:users,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Set default values if not provided
        $this->merge([
            'status' => $this->status ?? 'backlog',
            'priority' => $this->priority ?? 'medium',
        ]);
    }
}
