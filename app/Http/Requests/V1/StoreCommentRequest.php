<?php

namespace App\Http\Requests\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use App\Models\Comment;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        // Check if user can create comments on this task
        return $this->user()->can('create', [Comment::class, $task]);
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:5000'],
            'parent_id' => ['nullable', 'exists:comments,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // If parent_id is provided, validate it belongs to the same task
        if ($this->has('parent_id') && $this->parent_id) {
            $parent = Comment::find($this->parent_id);
            if ($parent && $parent->task_id != $this->route('task')->id) {
                $this->merge([
                    'parent_id' => null,
                ]);
            }
        }
    }
}
