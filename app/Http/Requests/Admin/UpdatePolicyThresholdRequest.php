<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePolicyThresholdRequest extends FormRequest
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
            'green_max_pct' => ['required', 'numeric', 'min:0', 'max:500'],
            'yellow_max_pct' => ['required', 'numeric', 'min:0', 'max:500', 'gte:green_max_pct'],
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
            'green_max_pct.min' => __('Green zone limit cannot be negative.'),
            'green_max_pct.max' => __('Green zone limit cannot exceed 500%.'),
            'yellow_max_pct.min' => __('Yellow zone limit cannot be negative.'),
            'yellow_max_pct.max' => __('Yellow zone limit cannot exceed 500%.'),
            'yellow_max_pct.gte' => __('Yellow zone limit must be greater than or equal to the green zone limit.'),
            'department_id.exists' => __('Selected department does not exist.'),
        ];
    }
}
