<?php

namespace App\Http\Requests\Overtime;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BulkApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user !== null && ($user->isManager() || $user->isAdmin());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'groups' => ['required', 'array', 'min:1', 'max:50'],
            'groups.*.section_id' => ['required', 'integer', 'exists:sections,id'],
            'groups.*.date' => ['required', 'date'],
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

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'groups.required' => __('Daftar grup lembur wajib dipilih.'),
            'groups.min' => __('Minimal satu grup harus dipilih.'),
            'groups.max' => __('Maksimal 50 grup dalam satu proses persetujuan massal.'),
            'action.required' => __('Aksi persetujuan wajib ditentukan.'),
            'action.in' => __('Aksi harus berupa APPROVED atau REJECTED.'),
            'rejection_reason.required_if' => __('Alasan penolakan massal wajib diisi (minimal 5 karakter).'),
        ];
    }
}
