<?php

namespace Database\Factories;

use App\Models\OperationalCalendar;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<OperationalCalendar>
 */
class OperationalCalendarFactory extends Factory
{
    protected $model = OperationalCalendar::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = Carbon::now('Asia/Jakarta')->subDays(fake()->unique()->numberBetween(0, 364))->startOfDay();

        return [
            'calendar_date' => $date->toDateString(),
            'day_type' => $date->isWeekend() ? 'HLR' : 'HKN',
            'is_holiday' => $date->isWeekend(),
            'holiday_name' => $date->isWeekend()
                ? ($date->isSaturday() ? 'Sabtu Libur' : 'Minggu Libur')
                : null,
            'description' => $date->isWeekend() ? 'Akhir Pekan' : 'Hari Kerja Normal',
            'created_at' => now('Asia/Jakarta'),
        ];
    }

    /**
     * Ensure a specific calendar date exists (idempotent for tests/seeders).
     */
    public function forDate(string|Carbon $date): static
    {
        $carbon = $date instanceof Carbon
            ? $date->copy()->timezone('Asia/Jakarta')->startOfDay()
            : Carbon::parse($date, 'Asia/Jakarta')->startOfDay();

        return $this->state(fn () => [
            'calendar_date' => $carbon->toDateString(),
            'day_type' => $carbon->isWeekend() ? 'HLR' : 'HKN',
            'is_holiday' => $carbon->isWeekend(),
            'holiday_name' => $carbon->isWeekend()
                ? ($carbon->isSaturday() ? 'Sabtu Libur' : 'Minggu Libur')
                : null,
            'description' => $carbon->isWeekend() ? 'Akhir Pekan' : 'Hari Kerja Normal',
        ]);
    }

    /**
     * Mark as a normal workday (HKN).
     */
    public function workday(): static
    {
        return $this->state(fn () => [
            'day_type' => 'HKN',
            'is_holiday' => false,
            'holiday_name' => null,
            'description' => 'Hari Kerja Normal',
        ]);
    }

    /**
     * Mark as a holiday / rest day (HLR).
     */
    public function holiday(?string $name = null): static
    {
        return $this->state(fn () => [
            'day_type' => 'HLR',
            'is_holiday' => true,
            'holiday_name' => $name ?? 'Hari Libur',
            'description' => 'Hari Libur Resmi',
        ]);
    }
}
