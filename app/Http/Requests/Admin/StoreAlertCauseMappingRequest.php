<?php

namespace App\Http\Requests\Admin;

use App\Enums\AlertCause;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlertCauseMappingRequest extends FormRequest
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
            'mappings' => ['required', 'array', 'min:1', 'max:'.count(AlertCause::cases())],
            'mappings.*.cause' => ['required', 'distinct', Rule::enum(AlertCause::class)],
            'mappings.*.pic_department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where('is_active', true),
            ],
            'mappings.*.is_active' => ['required', 'boolean'],
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
            'mappings.*.cause.distinct' => __('Each cause can only appear once.'),
            'mappings.*.pic_department_id.exists' => __('Choose an active department as the PIC.'),
        ];
    }
}
