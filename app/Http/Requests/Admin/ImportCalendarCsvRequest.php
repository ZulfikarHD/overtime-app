<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ImportCalendarCsvRequest extends FormRequest
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
            'rows.*.date' => ['required', 'date_format:Y-m-d'],
            'rows.*.holiday_name' => ['required', 'string', 'max:100'],
            'rows.*.description' => ['nullable', 'string', 'max:255'],
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
            'rows.required' => __('Tidak ada baris data hari libur yang dipilih untuk diimport.'),
            'rows.min' => __('Minimal satu baris data hari libur valid diperlukan untuk import.'),
        ];
    }
}
