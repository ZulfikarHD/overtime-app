<?php

namespace App\Http\Requests;

use App\Models\AlertFollowUp;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Query-string filters of the Early Warning inbox.
 *
 * Invalid filters never break the page: the user lands on the default inbox with an error message.
 *
 * @phpstan-type InboxFilters array{
 *     department_id: ?int,
 *     section_id: ?int,
 *     date_from: string,
 *     date_to: string,
 *     zone: 'all'|'yellow'|'red',
 *     status: 'all'|'none'|'draft'|'sent',
 *     page: int
 * }
 */
class EarlyWarningInboxRequest extends FormRequest
{
    /**
     * Length of the default date range (today and the six days before it).
     */
    public const DEFAULT_RANGE_DAYS = 7;

    /**
     * Longest date range one inbox request may scan.
     */
    public const MAX_RANGE_DAYS = 93;

    /**
     * Only admin, manager and team leader may open the inbox.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AlertFollowUp::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'department_id' => ['nullable', 'integer', 'min:1'],
            'section_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'zone' => ['nullable', Rule::in(['all', 'yellow', 'red'])],
            'status' => ['nullable', Rule::in(['all', 'none', 'draft', 'sent'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /**
     * The (defaulted) date range must be ordered and no longer than MAX_RANGE_DAYS.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['date_from', 'date_to'])) {
                    return;
                }

                [$from, $to] = $this->dateRange();

                if ($from->gt($to) || $from->diffInDays($to) >= self::MAX_RANGE_DAYS) {
                    $validator->errors()->add('date_to', __('The date range is invalid.'));
                }
            },
        ];
    }

    /**
     * Validated filters with defaults applied (last 7 days up to today, Asia/Jakarta).
     *
     * @return InboxFilters
     */
    public function filters(): array
    {
        [$from, $to] = $this->dateRange();

        return [
            'department_id' => $this->filled('department_id') ? $this->integer('department_id') : null,
            'section_id' => $this->filled('section_id') ? $this->integer('section_id') : null,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'zone' => $this->input('zone') ?? 'all',
            'status' => $this->input('status') ?? 'all',
            'page' => max(1, $this->integer('page', 1)),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function dateRange(): array
    {
        $today = Carbon::now('Asia/Jakarta')->startOfDay();

        $to = $this->filled('date_to')
            ? Carbon::createFromFormat('Y-m-d', (string) $this->input('date_to'), 'Asia/Jakarta')->startOfDay()
            : $today;

        $from = $this->filled('date_from')
            ? Carbon::createFromFormat('Y-m-d', (string) $this->input('date_from'), 'Asia/Jakarta')->startOfDay()
            : $to->copy()->subDays(self::DEFAULT_RANGE_DAYS - 1);

        return [$from, $to];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect()
                ->route('early-warning.index')
                ->with('error', __('The inbox filters are invalid. The default view is shown.')),
        );
    }
}
