<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCalendarDayRequest extends FormRequest
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
            'day_type' => ['required', 'string', 'in:HKN,HLR'],
            'is_holiday' => ['nullable', 'boolean'],
            'holiday_name' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
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
            'day_type.required' => __('Tipe hari (HKN / HLR) wajib dipilih.'),
            'day_type.in' => __('Tipe hari harus berupa HKN (Hari Kerja Normal) atau HLR (Hari Libur/Istirahat).'),
            'holiday_name.max' => __('Nama hari libur maksimal 100 karakter.'),
            'description.max' => __('Keterangan maksimal 255 karakter.'),
        ];
    }
}
