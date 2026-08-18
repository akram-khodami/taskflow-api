<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'members' => ['nullable', 'array'],
            'members.*' => ['exists:users,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('members')) {
            $members = array_unique($this->members);
            $this->merge([
                'members' => $members,
            ]);
        }
    }
}
