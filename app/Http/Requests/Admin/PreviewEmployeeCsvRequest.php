<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PreviewEmployeeCsvRequest extends FormRequest
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
            'file' => ['required_without:csv_content', 'nullable', 'file', 'mimes:csv,txt', 'max:5120'],
            'csv_content' => ['required_without:file', 'nullable', 'string'],
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
            'file.required' => __('Please select a CSV file to upload.'),
            'file.mimes' => __('The uploaded file must be a valid CSV file.'),
            'file.max' => __('CSV file size cannot exceed 5MB.'),
        ];
    }
}
