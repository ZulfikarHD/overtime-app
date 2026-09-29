<?php

namespace App\Services;

use App\Models\ShiftSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ShiftScheduleService
{
    public const MAX_CHANGES_PER_YEAR = 3;

    /**
     * Default plant shift windows when no schedule row exists yet.
     *
     * @return array{
     *     id: null,
     *     name: string,
     *     effective_from: string,
     *     effective_until: null,
     *     shifts: list<array{number: int, start: string, end: string}>
     * }
     */
    public function defaultPayload(): array
    {
        return [
            'id' => null,
            'name' => __('Standard Plant Shift'),
            'effective_from' => now('Asia/Jakarta')->startOfYear()->toDateString(),
            'effective_until' => null,
            'shifts' => [
                ['number' => 1, 'start' => '07:00', 'end' => '15:00'],
                ['number' => 2, 'start' => '15:00', 'end' => '23:00'],
                ['number' => 3, 'start' => '23:00', 'end' => '07:00'],
            ],
        ];
    }

    /**
     * Resolve the schedule that is active on the given date (Jakarta).
     */
    public function getActive(?Carbon $date = null): ?ShiftSchedule
    {
        $day = ($date ?? now('Asia/Jakarta'))->copy()->timezone('Asia/Jakarta')->startOfDay();

        return ShiftSchedule::query()
            ->activeOn($day)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Shared Inertia payload for live clock / shift badge.
     *
     * @return array{
     *     id: int|null,
     *     name: string,
     *     effective_from: string,
     *     effective_until: string|null,
     *     shifts: list<array{number: int, start: string, end: string}>
     * }
     */
    public function getActivePayload(?Carbon $date = null): array
    {
        $schedule = $this->getActive($date);

        if ($schedule === null) {
            return $this->defaultPayload();
        }

        return $this->toPayload($schedule);
    }

    /**
     * @return array{
     *     id: int|null,
     *     name: string,
     *     effective_from: string,
     *     effective_until: string|null,
     *     shifts: list<array{number: int, start: string, end: string}>
     * }
     */
    public function toPayload(ShiftSchedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'effective_from' => $schedule->effective_from->toDateString(),
            'effective_until' => $schedule->effective_until?->toDateString(),
            'shifts' => $schedule->shiftsPayload(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listForAdmin(): Collection
    {
        return ShiftSchedule::query()
            ->with('creator:id,name')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ShiftSchedule $schedule) => $this->toAdminRow($schedule));
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminRow(ShiftSchedule $schedule): array
    {
        $today = now('Asia/Jakarta')->toDateString();
        $from = $schedule->effective_from->toDateString();
        $until = $schedule->effective_until?->toDateString();

        $isActive = $from <= $today && ($until === null || $until >= $today);
        $isUpcoming = $from > $today;
        $isPast = $until !== null && $until < $today;

        return [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'effective_from' => $from,
            'effective_until' => $until,
            'shift_1_start' => $schedule->formatTime('shift_1_start'),
            'shift_1_end' => $schedule->formatTime('shift_1_end'),
            'shift_2_start' => $schedule->formatTime('shift_2_start'),
            'shift_2_end' => $schedule->formatTime('shift_2_end'),
            'shift_3_start' => $schedule->formatTime('shift_3_start'),
            'shift_3_end' => $schedule->formatTime('shift_3_end'),
            'created_by' => $schedule->created_by,
            'creator_name' => $schedule->creator?->name,
            'is_active' => $isActive,
            'is_upcoming' => $isUpcoming,
            'is_past' => $isPast,
            'updated_at' => $schedule->updated_at?->timezone('Asia/Jakarta')->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $createdBy = null): ShiftSchedule
    {
        $this->closeOpenEndedScheduleBefore((string) $data['effective_from']);

        $this->assertWithinYearQuota(
            (string) $data['effective_from'],
            isset($data['effective_until']) ? (string) $data['effective_until'] : null,
        );
        $this->assertNoOverlap(
            (string) $data['effective_from'],
            isset($data['effective_until']) ? (string) $data['effective_until'] : null,
        );

        return ShiftSchedule::query()->create([
            ...$this->normalizeTimes($data),
            'created_by' => $createdBy,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ShiftSchedule $schedule, array $data): ShiftSchedule
    {
        $from = (string) ($data['effective_from'] ?? $schedule->effective_from->toDateString());
        $until = array_key_exists('effective_until', $data)
            ? ($data['effective_until'] !== null && $data['effective_until'] !== ''
                ? (string) $data['effective_until']
                : null)
            : $schedule->effective_until?->toDateString();

        $this->assertWithinYearQuota($from, $until, $schedule->id);
        $this->assertNoOverlap($from, $until, $schedule->id);

        $schedule->update($this->normalizeTimes([
            ...$data,
            'effective_from' => $from,
            'effective_until' => $until,
        ]));

        return $schedule->refresh();
    }

    public function delete(ShiftSchedule $schedule): void
    {
        $schedule->delete();
    }

    /**
     * Ensure at most 3 schedule periods intersect a calendar year.
     */
    public function assertWithinYearQuota(string $from, ?string $until, ?int $ignoreId = null): void
    {
        $fromDate = Carbon::parse($from, 'Asia/Jakarta')->startOfDay();
        $untilDate = $until !== null
            ? Carbon::parse($until, 'Asia/Jakarta')->startOfDay()
            : null;

        $years = [$fromDate->year];
        if ($untilDate !== null) {
            for ($year = $fromDate->year; $year <= $untilDate->year; $year++) {
                $years[] = $year;
            }
        } else {
            // Open-ended: check from-year and current/next year horizon
            $years[] = max($fromDate->year, now('Asia/Jakarta')->year);
        }

        foreach (array_unique($years) as $year) {
            $yearStart = Carbon::create($year, 1, 1, 0, 0, 0, 'Asia/Jakarta')->startOfDay();
            $yearEnd = Carbon::create($year, 12, 31, 0, 0, 0, 'Asia/Jakarta')->startOfDay();

            $count = ShiftSchedule::query()
                ->when($ignoreId !== null, function ($q) use ($ignoreId) {
                    $q->where('id', '!=', $ignoreId);
                })
                ->whereDate('effective_from', '<=', $yearEnd)
                ->where(function ($q) use ($yearStart) {
                    $q->whereNull('effective_until')
                        ->orWhereDate('effective_until', '>=', $yearStart);
                })
                ->count();

            // The new/updated row will also count once saved
            $intersectsYear = $fromDate->lte($yearEnd)
                && ($untilDate === null || $untilDate->gte($yearStart));

            if ($intersectsYear && ($count + 1) > self::MAX_CHANGES_PER_YEAR) {
                throw ValidationException::withMessages([
                    'effective_from' => __(
                        'Shift times can change at most :max times per calendar year (:year already has :count period(s)).',
                        [
                            'max' => self::MAX_CHANGES_PER_YEAR,
                            'year' => $year,
                            'count' => $count,
                        ],
                    ),
                ]);
            }
        }
    }

    public function assertNoOverlap(string $from, ?string $until, ?int $ignoreId = null): void
    {
        $fromDate = Carbon::parse($from, 'Asia/Jakarta')->startOfDay();
        $untilDate = $until !== null
            ? Carbon::parse($until, 'Asia/Jakarta')->startOfDay()
            : null;

        $overlap = ShiftSchedule::query()
            ->when($ignoreId !== null, function ($q) use ($ignoreId) {
                $q->where('id', '!=', $ignoreId);
            })
            ->where(function ($q) use ($fromDate, $untilDate) {
                // Existing starts before new ends (or new is open-ended)
                $q->whereDate('effective_from', '<=', $untilDate?->toDateString() ?? '9999-12-31')
                    ->where(function ($inner) use ($fromDate) {
                        $inner->whereNull('effective_until')
                            ->orWhereDate('effective_until', '>=', $fromDate);
                    });
            })
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'effective_from' => __('This effective period overlaps an existing shift schedule.'),
            ]);
        }
    }

    /**
     * When creating a new open period, auto-close the previous open-ended schedule
     * the day before the new effective_from.
     */
    protected function closeOpenEndedScheduleBefore(string $from): void
    {
        $fromDate = Carbon::parse($from, 'Asia/Jakarta')->startOfDay();
        $closeOn = $fromDate->copy()->subDay();

        ShiftSchedule::query()
            ->whereNull('effective_until')
            ->whereDate('effective_from', '<', $fromDate)
            ->update(['effective_until' => $closeOn->toDateString()]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeTimes(array $data): array
    {
        $keys = [
            'name',
            'effective_from',
            'effective_until',
            'shift_1_start',
            'shift_1_end',
            'shift_2_start',
            'shift_2_end',
            'shift_3_start',
            'shift_3_end',
        ];

        $normalized = [];
        foreach ($keys as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            if (str_ends_with($key, '_start') || str_ends_with($key, '_end')) {
                $normalized[$key] = substr((string) $value, 0, 5);
            } elseif ($key === 'effective_until' && ($value === '' || $value === null)) {
                $normalized[$key] = null;
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
