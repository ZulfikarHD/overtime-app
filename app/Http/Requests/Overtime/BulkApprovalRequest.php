<?php

namespace App\Http\Requests\Overtime;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BulkApprovalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user !== null && ($user->isManager() || $user->isAdmin());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'submission_ids' => ['required', 'array', 'min:1', 'max:50'],
            'submission_ids.*' => ['required', 'integer', 'exists:overtime_submissions,id'],
            'action' => ['required', 'string', 'in:APPROVED,REJECTED,approved,rejected'],
            'rejection_reason' => [
                'required_if:action,REJECTED,rejected',
                'nullable',
                'string',
                'max:1000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $action = strtoupper((string) ($this->input('action') ?? ''));
                    if ($action === 'REJECTED') {
                        $trimmed = trim((string) $value);
                        if ($trimmed === '' || mb_strlen($trimmed) < 5) {
                            $fail(__('Alasan penolakan massal wajib diisi (minimal 5 karakter).'));
                        }
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'submission_ids.required' => __('Daftar pengajuan lembur wajib dipilih.'),
            'submission_ids.min' => __('Minimal satu pengajuan lembur harus dipilih.'),
            'submission_ids.max' => __('Maksimal 50 pengajuan lembur dalam satu proses persetujuan massal.'),
            'submission_ids.*.required' => __('ID pengajuan lembur wajib diisi.'),
            'submission_ids.*.integer' => __('ID pengajuan lembur harus berupa angka.'),
            'submission_ids.*.exists' => __('Salah satu pengajuan lembur tidak valid atau tidak ditemukan.'),
            'action.required' => __('Aksi persetujuan wajib ditentukan.'),
            'action.in' => __('Aksi persetujuan harus berupa APPROVED atau REJECTED.'),
            'rejection_reason.required_if' => __('Alasan penolakan massal wajib diisi (minimal 5 karakter).'),
            'rejection_reason.max' => __('Alasan penolakan maksimal 1000 karakter.'),
        ];
    }
}
