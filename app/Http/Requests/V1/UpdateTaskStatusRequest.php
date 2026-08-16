<?php

namespace App\Http\Requests\V1;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskStatusRequest extends FormRequest
{
    //todo:use enum instead of constant if you had time
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $this->user()->can('updateStatus', $task);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(Task::STATUSES))],
        ];
    }
}
