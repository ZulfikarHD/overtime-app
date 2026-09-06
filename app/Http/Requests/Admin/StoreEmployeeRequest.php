<?php

namespace App\Http\Requests\Admin;

use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
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
        if ($this->has('npk') && is_string($this->npk)) {
            $this->merge([
                'npk' => strtoupper(trim($this->npk)),
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
            'npk' => [
                'required',
                'string',
                'max:20',
                Rule::unique('employees', 'npk'),
                'regex:/^[A-Z0-9_\-]+$/',
            ],
            'full_name' => ['required', 'string', 'max:150'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'job_position' => ['required', 'string', 'max:100'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
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
            'npk.unique' => __('Employee NPK has already been registered.'),
            'npk.regex' => __('Employee NPK may only contain uppercase letters, numbers, underscores, and hyphens.'),
            'hourly_rate.min' => __('Hourly rate cannot be negative.'),
        ];
    }
}
