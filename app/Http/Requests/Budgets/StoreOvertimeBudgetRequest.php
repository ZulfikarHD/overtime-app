<?php

namespace App\Http\Requests\Budgets;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOvertimeBudgetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'fiscal_year' => ['required', 'integer', 'min:2020', 'max:2050'],
            'fiscal_month' => ['required', 'integer', 'min:1', 'max:12'],
            'planned_hours' => ['required', 'numeric', 'min:0', 'max:10000'],
            'week1_planned_hours' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'week2_planned_hours' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'week3_planned_hours' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'week4_planned_hours' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'week5_planned_hours' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ];
    }
}
