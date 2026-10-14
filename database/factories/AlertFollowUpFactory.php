<?php

namespace Database\Factories;

use App\Enums\AlertFollowUpStatus;
use App\Enums\BurnZone;
use App\Models\AlertFollowUp;
use App\Models\Department;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<AlertFollowUp>
 */
class AlertFollowUpFactory extends Factory
{
    protected $model = AlertFollowUp::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'section_id' => null,
            'fiscal_year' => 2026,
            'fiscal_month' => 10,
            'zone' => BurnZone::Red,
            'index_pct' => 95.50,
            'what_text' => 'Index harian melewati batas Kuning',
            'why_text' => 'Volume produksi naik, jumlah MP tidak bertambah',
            'when_date' => '2026-10-14',
            'where_location' => 'Line Assembly',
            'who_department_id' => Department::factory(),
            'how_text' => 'Tambah 5 orang shift malam selama 1 minggu',
            'how_much_cost_idr' => 7500000,
            'how_much_hours' => 120.00,
            'status' => AlertFollowUpStatus::Draft,
            'created_by' => User::factory(),
            'sent_by' => null,
            'sent_at' => null,
        ];
    }

    /**
     * Link the follow-up to a concrete alert (section + date).
     */
    public function forAlert(Section $section, Carbon|string $date): static
    {
        $alertDate = $date instanceof Carbon
            ? $date->copy()->startOfDay()
            : Carbon::parse($date, 'Asia/Jakarta')->startOfDay();

        return $this->state(fn (array $attributes) => [
            'department_id' => $section->department_id,
            'section_id' => $section->id,
            'alert_date' => $alertDate->toDateString(),
            'fiscal_year' => $alertDate->year,
            'fiscal_month' => $alertDate->month,
        ]);
    }

    /**
     * Mark the follow-up as already sent (read-only).
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AlertFollowUpStatus::Sent,
            'sent_by' => $attributes['created_by'],
            'sent_at' => now('Asia/Jakarta'),
        ]);
    }
}
