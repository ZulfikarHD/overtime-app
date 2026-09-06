<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
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
        /** @var User|int|string $targetUser */
        $targetUser = $this->route('user');
        $userId = $targetUser instanceof User ? $targetUser->id : (int) $targetUser;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'password' => ['nullable', 'string', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'npk' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('users', 'npk')->ignore($userId),
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
                /** @var User|int|string $targetUser */
                $targetUser = $this->route('user');
                $userId = $targetUser instanceof User ? $targetUser->id : (int) $targetUser;
                $currentUser = $this->user();

                if ($currentUser && $currentUser->id === $userId) {
                    if ($this->has('is_active') && ! $this->boolean('is_active')) {
                        $validator->errors()->add('is_active', __('You cannot deactivate your own account.'));
                    }

                    if ($this->has('role')) {
                        $requestedRole = $this->input('role');
                        $roleVal = $requestedRole instanceof UserRole ? $requestedRole->value : (string) $requestedRole;
                        if ($roleVal !== UserRole::Admin->value) {
                            $validator->errors()->add('role', __('You cannot remove your own administrator privileges.'));
                        }
                    }
                }

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
