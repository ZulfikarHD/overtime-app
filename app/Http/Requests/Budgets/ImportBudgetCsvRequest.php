<?php

namespace App\Http\Requests\Budgets;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportBudgetCsvRequest extends FormRequest
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
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.section_id' => ['required', 'integer'],
            'rows.*.department_id' => ['required', 'integer'],
            'rows.*.fiscal_year' => ['required', 'integer'],
            'rows.*.fiscal_month' => ['required', 'integer'],
            'rows.*.planned_hours' => ['required', 'numeric', 'min:0'],
        ];
    }
}
