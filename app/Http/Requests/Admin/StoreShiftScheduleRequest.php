<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreShiftScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'shift_1_start' => ['required', 'date_format:H:i'],
            'shift_1_end' => ['required', 'date_format:H:i'],
            'shift_2_start' => ['required', 'date_format:H:i'],
            'shift_2_end' => ['required', 'date_format:H:i'],
            'shift_3_start' => ['required', 'date_format:H:i'],
            'shift_3_end' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('Schedule name is required.'),
            'effective_from.required' => __('Effective from date is required.'),
            'effective_until.after_or_equal' => __('Effective until must be on or after effective from.'),
            'shift_1_start.date_format' => __('Shift 1 start must use HH:MM format.'),
            'shift_1_end.date_format' => __('Shift 1 end must use HH:MM format.'),
            'shift_2_start.date_format' => __('Shift 2 start must use HH:MM format.'),
            'shift_2_end.date_format' => __('Shift 2 end must use HH:MM format.'),
            'shift_3_start.date_format' => __('Shift 3 start must use HH:MM format.'),
            'shift_3_end.date_format' => __('Shift 3 end must use HH:MM format.'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $s1Start = (string) $this->input('shift_1_start');
            $s1End = (string) $this->input('shift_1_end');
            $s2Start = (string) $this->input('shift_2_start');
            $s2End = (string) $this->input('shift_2_end');
            $s3Start = (string) $this->input('shift_3_start');
            $s3End = (string) $this->input('shift_3_end');

            if ($s1End !== $s2Start) {
                $validator->errors()->add(
                    'shift_2_start',
                    __('Shift 2 must start when Shift 1 ends.'),
                );
            }

            if ($s2End !== $s3Start) {
                $validator->errors()->add(
                    'shift_3_start',
                    __('Shift 3 must start when Shift 2 ends.'),
                );
            }

            if ($s3End !== $s1Start) {
                $validator->errors()->add(
                    'shift_3_end',
                    __('Shift 3 must end when Shift 1 starts (24-hour coverage).'),
                );
            }
        });
    }
}
