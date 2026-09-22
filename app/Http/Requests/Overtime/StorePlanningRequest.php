<?php

namespace App\Http\Requests\Overtime;

use Illuminate\Foundation\Http\FormRequest;

class StorePlanningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(['admin', 'manager', 'team_leader']) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'fiscal_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'fiscal_month' => ['required', 'integer', 'min:1', 'max:12'],
            'notes' => ['nullable', 'string', 'max:1000'],

            // Rows: one entry per employee per day
            'items' => ['required', 'array', 'min:1'],
            'items.*.employee_id' => ['required', 'integer', 'exists:employees,id'],
            'items.*.plan_date' => ['required', 'date'],
            'items.*.hours_production' => ['required', 'numeric', 'min:0', 'max:24'],
            'items.*.hours_tpm' => ['required', 'numeric', 'min:0', 'max:24'],
            'items.*.hours_project' => ['required', 'numeric', 'min:0', 'max:24'],
            'items.*.hours_others' => ['required', 'numeric', 'min:0', 'max:24'],
        ];
    }
}
