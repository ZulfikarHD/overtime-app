<?php

namespace App\Http\Requests\Overtime;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApproveOvertimeItemsRequest extends FormRequest
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
            'decisions' => ['required', 'array', 'min:1'],
            'decisions.*.item_id' => ['required', 'integer', 'exists:overtime_items,id'],
            'decisions.*.action' => ['required', 'string', 'in:APPROVED,REJECTED,approved,rejected'],
            'decisions.*.rejection_reason' => [
                'nullable',
                'string',
                'max:1000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (preg_match('/decisions\.(\d+)\.rejection_reason/', $attribute, $matches)) {
                        $idx = $matches[1];
                        $action = strtoupper((string) ($this->input("decisions.{$idx}.action") ?? ''));
                        if ($action === 'REJECTED') {
                            $trimmed = trim((string) $value);
                            if ($trimmed === '' || mb_strlen($trimmed) < 5) {
                                $fail(__('Alasan penolakan wajib diisi (minimal 5 karakter) untuk setiap item yang ditolak.'));
                            }
                        }
                    }
                },
            ],
            'decisions.*.lock_version' => ['nullable', 'integer'],
            'decisions.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decisions.required' => __('Keputusan item lembur wajib dikirimkan.'),
            'decisions.min' => __('Minimal satu keputusan item lembur harus dipilih.'),
            'decisions.*.item_id.required' => __('ID item lembur wajib diisi.'),
            'decisions.*.item_id.exists' => __('Item lembur tidak valid atau tidak ditemukan.'),
            'decisions.*.action.required' => __('Aksi persetujuan wajib ditentukan.'),
            'decisions.*.action.in' => __('Aksi persetujuan harus berupa APPROVED atau REJECTED.'),
        ];
    }
}
