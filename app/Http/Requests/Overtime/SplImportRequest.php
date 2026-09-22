<?php

namespace App\Http\Requests\Overtime;

use Illuminate\Foundation\Http\FormRequest;

class SplImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gate: only admin and manager may import SPL files
        return $this->user()?->hasRole(['admin', 'manager']) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls',
                'max:10240', // 10 MB
            ],
            'fiscal_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'fiscal_month' => ['required', 'integer', 'min:1', 'max:12'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => __('File SPL wajib diunggah.'),
            'file.mimes' => __('File harus berformat Excel (.xlsx atau .xls).'),
            'file.max' => __('Ukuran file maksimal 10 MB.'),
            'fiscal_year.required' => __('Tahun wajib diisi.'),
            'fiscal_month.required' => __('Bulan wajib diisi.'),
        ];
    }
}
