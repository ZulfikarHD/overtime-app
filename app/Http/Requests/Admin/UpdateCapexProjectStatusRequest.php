<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCapexProjectStatusRequest extends FormRequest
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
            'status' => [
                'required',
                'string',
                Rule::in(['PLANNING', 'ACTIVE', 'ON_HOLD', 'COMPLETED', 'CLOSED']),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
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
            'status.required' => __('Status proyek wajib dipilih.'),
            'status.in' => __('Status proyek yang dipilih tidak valid.'),
            'notes.max' => __('Catatan transisi status maksimal 1000 karakter.'),
        ];
    }
}
