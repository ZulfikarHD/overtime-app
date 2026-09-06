<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
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
        if ($this->has('code') && is_string($this->code)) {
            $this->merge([
                'code' => strtoupper(trim($this->code)),
            ]);
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
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('departments', 'code'),
                'regex:/^[A-Z0-9_\-]+$/',
            ],
            'name' => ['required', 'string', 'max:100'],
            'cost_center_code' => ['required', 'string', 'max:50'],
            'default_hourly_rate' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'is_active' => ['boolean'],
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
            'code.unique' => __('Department code has already been registered.'),
            'code.regex' => __('Department code may only contain uppercase letters, numbers, underscores, and hyphens.'),
            'default_hourly_rate.min' => __('Default hourly rate cannot be negative.'),
        ];
    }
}
