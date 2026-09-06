<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePolicyThresholdRequest extends FormRequest
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
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id'),
            ],
            'weekly_soft_limit_hours' => ['required', 'numeric', 'min:0', 'max:168'],
            'consecutive_weeks_alert' => ['required', 'integer', 'min:1', 'max:52'],
            'spkl_grace_period_days' => ['required', 'integer', 'min:0', 'max:30'],
            'burn_warning_pct' => ['required', 'numeric', 'min:0', 'max:500'],
            'burn_danger_pct' => ['required', 'numeric', 'min:0', 'max:500', 'gte:burn_warning_pct'],
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
            'weekly_soft_limit_hours.min' => __('Weekly soft limit cannot be negative.'),
            'weekly_soft_limit_hours.max' => __('Weekly soft limit cannot exceed 168 hours.'),
            'consecutive_weeks_alert.min' => __('Consecutive weeks alert must be at least 1 week.'),
            'consecutive_weeks_alert.max' => __('Consecutive weeks alert cannot exceed 52 weeks.'),
            'spkl_grace_period_days.min' => __('SPKL grace period cannot be negative.'),
            'spkl_grace_period_days.max' => __('SPKL grace period cannot exceed 30 days.'),
            'burn_warning_pct.min' => __('Burn warning percentage cannot be negative.'),
            'burn_warning_pct.max' => __('Burn warning percentage cannot exceed 500%.'),
            'burn_danger_pct.min' => __('Burn danger percentage cannot be negative.'),
            'burn_danger_pct.max' => __('Burn danger percentage cannot exceed 500%.'),
            'burn_danger_pct.gte' => __('Burn danger percentage must be greater than or equal to burn warning percentage.'),
            'department_id.exists' => __('Selected department does not exist.'),
        ];
    }
}
