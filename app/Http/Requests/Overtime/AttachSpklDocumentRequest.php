<?php

namespace App\Http\Requests\Overtime;

use App\Models\OvertimeSubmission;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AttachSpklDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        if (! $user) {
            return false;
        }

        /** @var OvertimeSubmission|null $submission */
        $submission = $this->route('submission');

        if (! $submission instanceof OvertimeSubmission) {
            return false;
        }

        return $user->canAccessSection($submission->section_id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'nullable',
                'file',
                'mimes:pdf,jpeg,jpg,png',
                'max:3072', // 3 MB in KB
            ],
            'spkl_number' => [
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $hasFile = $this->hasFile('file') && $this->file('file')?->isValid();
            $hasNumber = filled($this->input('spkl_number'));

            if (! $hasFile && ! $hasNumber) {
                $v->errors()->add('file', __('Lampirkan berkas dokumen SPKL (PDF/Gambar) atau masukkan nomor fisik SPKL.'));
            }
        });
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => __('Format berkas SPKL harus berupa PDF, JPEG, atau PNG.'),
            'file.max' => __('Ukuran berkas SPKL maksimal 3 MB.'),
            'spkl_number.max' => __('Nomor dokumen SPKL maksimal 100 karakter.'),
        ];
    }
}
