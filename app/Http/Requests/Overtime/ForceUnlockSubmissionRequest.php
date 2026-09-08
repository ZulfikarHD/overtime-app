<?php

namespace App\Http\Requests\Overtime;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ForceUnlockSubmissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user !== null && $user->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'max:1000',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $trimmed = trim((string) $value);
                    if ($trimmed === '' || mb_strlen($trimmed) < 5) {
                        $fail(__('Alasan pembukaan kunci wajib diisi minimal 5 karakter.'));
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
            'reason.required' => __('Alasan pembukaan kunci wajib diisi minimal 5 karakter.'),
            'reason.max' => __('Alasan pembukaan kunci maksimal 1000 karakter.'),
        ];
    }
}
