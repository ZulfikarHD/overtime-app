<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCapexProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user !== null && ($user->isAdmin() || $user->isManager());
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        /** @var User|null $user */
        $user = $this->user();

        $merges = [];

        if ($this->has('asset_code') && is_string($this->asset_code)) {
            $trimmed = trim($this->asset_code);
            $merges['asset_code'] = $trimmed !== '' ? strtoupper($trimmed) : null;
        }

        // If manager, scope department to their assigned department
        if ($user && $user->isManager() && $user->department_id) {
            $merges['department_id'] = $user->department_id;
        }

        if (! empty($merges)) {
            $this->merge($merges);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     * Note: project_code is immutable after creation and prohibited from updates.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'project_code' => ['prohibited'],
            'asset_code' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:200'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'allocated_labor_hours' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'allocated_labor_budget_idr' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'start_date' => ['required', 'date'],
            'target_end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'physical_progress_pct' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    /**
     * Custom validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'project_code.prohibited' => __('Kode proyek bersifat permanen dan tidak dapat diubah setelah dibuat demi menjaga jejak audit akuntansi.'),
            'name.required' => __('Nama proyek wajib diisi.'),
            'department_id.required' => __('Departemen pemilik proyek wajib dipilih.'),
            'department_id.exists' => __('Departemen yang dipilih tidak valid.'),
            'allocated_labor_hours.required' => __('Alokasi jam kerja lembur wajib diisi.'),
            'allocated_labor_hours.min' => __('Alokasi jam kerja lembur tidak boleh bernilai negatif.'),
            'allocated_labor_budget_idr.required' => __('Alokasi anggaran tenaga kerja (Rp) wajib diisi.'),
            'allocated_labor_budget_idr.min' => __('Alokasi anggaran tenaga kerja tidak boleh bernilai negatif.'),
            'start_date.required' => __('Tanggal mulai proyek wajib diisi.'),
            'target_end_date.required' => __('Target tanggal selesai proyek wajib diisi.'),
            'target_end_date.after_or_equal' => __('Target tanggal selesai harus sama atau setelah tanggal mulai.'),
        ];
    }
}
