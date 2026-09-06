<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ImportEmployeeCsvRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.npk' => ['required', 'string', 'max:20'],
            'rows.*.full_name' => ['required', 'string', 'max:150'],
            'rows.*.department_id' => ['required', 'integer', 'exists:departments,id'],
            'rows.*.section_id' => ['required', 'integer', 'exists:sections,id'],
            'rows.*.job_position' => ['required', 'string', 'max:100'],
            'rows.*.hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'rows.*.is_valid' => ['nullable', 'boolean'],
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
            'rows.required' => __('No rows selected for import.'),
            'rows.min' => __('At least one valid employee row is required to import.'),
        ];
    }
}
