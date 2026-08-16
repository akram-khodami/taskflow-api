<?php

namespace App\Http\Requests\V1;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{

    //todo:use enum instead of constant if you had time
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $this->user()->can('update', $task);
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(array_keys(Task::STATUSES))],
            'priority' => ['nullable', Rule::in(array_keys(Task::PRIORITIES))],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'assignee_id' => ['nullable', 'exists:users,id'],
        ];
    }
}
