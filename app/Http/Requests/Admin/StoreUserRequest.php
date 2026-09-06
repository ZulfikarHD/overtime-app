<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('email') && is_string($this->email)) {
            $merge['email'] = strtolower(trim($this->email));
        }

        if ($this->has('npk') && is_string($this->npk)) {
            $merge['npk'] = trim($this->npk) !== '' ? strtoupper(trim($this->npk)) : null;
        }

        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'password' => ['required', 'string', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'npk' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('users', 'npk'),
            ],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function ($validator) {
                if ($this->filled('section_id') && ! $this->filled('department_id')) {
                    $validator->errors()->add('department_id', __('Department is required when a section is selected.'));
                }

                if ($this->filled(['department_id', 'section_id'])) {
                    $belongs = Section::where('id', $this->section_id)
                        ->where('department_id', $this->department_id)
                        ->exists();

                    if (! $belongs) {
                        $validator->errors()->add('section_id', __('The selected section does not belong to the selected department.'));
                    }
                }
            },
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => __('This email address is already in use.'),
            'npk.unique' => __('This NPK is already assigned to another user account.'),
        ];
    }
}
