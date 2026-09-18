<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\SpklDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<OvertimeSubmission>
 */
class OvertimeSubmissionFactory extends Factory
{
    protected $model = OvertimeSubmission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $operationalDate = Carbon::now('Asia/Jakarta')->subDays(fake()->numberBetween(1, 14))->toDateString();

        return [
            'submission_code' => $this->uniqueSubmissionCode($operationalDate),
            'submission_date' => $operationalDate,
            'operational_date' => $operationalDate,
            'day_type' => Carbon::parse($operationalDate, 'Asia/Jakarta')->isWeekend() ? 'HLR' : 'HKN',
            'department_id' => Department::factory(),
            'section_id' => function (array $attributes) {
                return Section::factory()->create([
                    'department_id' => $attributes['department_id'],
                ])->id;
            },
            'submitted_by_user_id' => User::factory(),
            'status' => 'SUBMITTED',
            'total_hours_cached' => 0.00,
            'submission_notes' => fake()->optional(0.4)->sentence(),
        ];
    }

    /**
     * Ensure the operational calendar row exists before insert (FK).
     */
    public function configure(): static
    {
        return $this->afterMaking(function (OvertimeSubmission $submission): void {
            $date = $submission->operational_date;

            if ($date === null) {
                return;
            }

            $dateString = $date instanceof \DateTimeInterface
                ? Carbon::instance($date)->timezone('Asia/Jakarta')->toDateString()
                : Carbon::parse((string) $date, 'Asia/Jakarta')->toDateString();

            if (OperationalCalendar::whereDate('calendar_date', $dateString)->exists()) {
                return;
            }

            OperationalCalendar::factory()->forDate($dateString)->create();
        });
    }

    /**
     * Draft submission (not yet sent for approval).
     */
    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'DRAFT']);
    }

    /**
     * Submitted and awaiting approval.
     */
    public function submitted(): static
    {
        return $this->state(fn () => ['status' => 'SUBMITTED']);
    }

    /**
     * Partially approved submission.
     */
    public function partiallyApproved(): static
    {
        return $this->state(fn () => ['status' => 'PARTIALLY_APPROVED']);
    }

    /**
     * Fully approved submission.
     */
    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'APPROVED']);
    }

    /**
     * Rejected submission.
     */
    public function rejected(): static
    {
        return $this->state(fn () => ['status' => 'REJECTED']);
    }

    /**
     * Bind submission to an existing department + section.
     */
    public function forSection(Section $section): static
    {
        return $this->state(fn () => [
            'department_id' => $section->department_id,
            'section_id' => $section->id,
        ]);
    }

    /**
     * Use a specific operational date (calendar row is auto-created).
     */
    public function onDate(string $date): static
    {
        $carbon = Carbon::parse($date, 'Asia/Jakarta');

        return $this->state(fn () => [
            'operational_date' => $carbon->toDateString(),
            'submission_date' => $carbon->toDateString(),
            'day_type' => $carbon->isWeekend() ? 'HLR' : 'HKN',
            'submission_code' => $this->uniqueSubmissionCode($carbon->toDateString()),
        ]);
    }

    /**
     * Create related overtime items after the submission is persisted.
     */
    public function withItems(int $count = 3, string $itemStatus = 'PENDING'): static
    {
        return $this->afterCreating(function (OvertimeSubmission $submission) use ($count, $itemStatus): void {
            $employees = Employee::query()
                ->where('section_id', $submission->section_id)
                ->where('is_active', true)
                ->inRandomOrder()
                ->limit($count)
                ->get();

            if ($employees->count() < $count) {
                $employees = $employees->merge(
                    Employee::factory()
                        ->count($count - $employees->count())
                        ->forDepartmentAndSection($submission->department_id, $submission->section_id)
                        ->create()
                );
            }

            $totalHours = 0.0;

            foreach ($employees as $employee) {
                $factory = OvertimeItem::factory()
                    ->forEmployee($employee)
                    ->state([
                        'overtime_submission_id' => $submission->id,
                        'status' => $itemStatus,
                    ]);

                if ($itemStatus === 'APPROVED') {
                    $factory = $factory->approved();
                } elseif ($itemStatus === 'REJECTED') {
                    $factory = $factory->rejected();
                }

                $item = $factory->create();
                $totalHours += (float) $item->hours_production
                    + (float) $item->hours_tpm
                    + (float) $item->hours_project
                    + (float) $item->hours_others;
            }

            $submission->update(['total_hours_cached' => round($totalHours, 2)]);
        });
    }

    /**
     * Create a linked SPKL document container.
     */
    public function withSpkl(string $status = 'PENDING'): static
    {
        return $this->afterCreating(function (OvertimeSubmission $submission) use ($status): void {
            $factory = SpklDocument::factory()->state([
                'overtime_submission_id' => $submission->id,
            ]);

            $factory = match ($status) {
                'ATTACHED' => $factory->attached(),
                'VERIFIED' => $factory->verified(),
                'OVERDUE' => $factory->overdue(),
                default => $factory->pending(),
            };

            $factory->create([
                'attached_by_user_id' => in_array($status, ['ATTACHED', 'VERIFIED'], true)
                    ? $submission->submitted_by_user_id
                    : null,
            ]);
        });
    }

    protected function uniqueSubmissionCode(string $operationalDate): string
    {
        $dateStr = Carbon::parse($operationalDate, 'Asia/Jakarta')->format('Ymd');
        $seq = fake()->unique()->numerify('###');

        return sprintf('OT-%s-DEMO-%s', $dateStr, $seq);
    }
}
