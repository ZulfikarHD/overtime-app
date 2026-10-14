<?php

namespace App\Http\Requests;

use App\Models\AlertFollowUp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlertFollowUpRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', AlertFollowUp::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Draft saves only require "what"; sending by email requires the full 5W2H.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $requiredWhenSending = Rule::requiredIf(fn (): bool => $this->boolean('send'));

        return [
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'what_text' => ['required', 'string', 'max:2000'],
            'why_text' => [$requiredWhenSending, 'nullable', 'string', 'max:2000'],
            'when_date' => [$requiredWhenSending, 'nullable', 'date'],
            'where_location' => ['nullable', 'string', 'max:255'],
            'who_department_id' => [$requiredWhenSending, 'nullable', 'integer', 'exists:departments,id'],
            'how_text' => [$requiredWhenSending, 'nullable', 'string', 'max:2000'],
            'how_much_cost_idr' => ['nullable', 'integer', 'min:0', 'max:99999999999'],
            'how_much_hours' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'send' => ['sometimes', 'boolean'],
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
            'what_text.required' => __('Describe the problem (What) before saving.'),
            'why_text.required' => __('Explain the cause (Why) before sending.'),
            'when_date.required' => __('Set the date (When) before sending.'),
            'who_department_id.required' => __('Choose the PIC department (Who) before sending.'),
            'how_text.required' => __('Describe the follow-up plan (How) before sending.'),
        ];
    }
}
