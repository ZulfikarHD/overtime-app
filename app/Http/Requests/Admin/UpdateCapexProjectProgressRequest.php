<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCapexProjectProgressRequest extends FormRequest
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'physical_progress_pct' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],
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
            'physical_progress_pct.required' => __('Kemajuan fisik proyek wajib diisi.'),
            'physical_progress_pct.numeric' => __('Kemajuan fisik proyek harus berupa angka.'),
            'physical_progress_pct.min' => __('Kemajuan fisik proyek minimal 0%.'),
            'physical_progress_pct.max' => __('Kemajuan fisik proyek maksimal 100%.'),
        ];
    }
}
