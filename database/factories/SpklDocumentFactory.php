<?php

namespace Database\Factories;

use App\Models\OvertimeSubmission;
use App\Models\SpklDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<SpklDocument>
 */
class SpklDocumentFactory extends Factory
{
    protected $model = SpklDocument::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'overtime_submission_id' => OvertimeSubmission::factory(),
            'spkl_number' => null,
            'file_path' => null,
            'file_name' => null,
            'file_size_bytes' => null,
            'mime_type' => null,
            'status' => 'PENDING',
            'due_date' => Carbon::now('Asia/Jakarta')->addWeekdays(2)->toDateString(),
            'attached_at' => null,
            'attached_by_user_id' => null,
        ];
    }

    /**
     * Pending physical SPKL attachment.
     */
    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'PENDING',
            'spkl_number' => null,
            'file_path' => null,
            'file_name' => null,
            'file_size_bytes' => null,
            'mime_type' => null,
            'attached_at' => null,
            'attached_by_user_id' => null,
        ]);
    }

    /**
     * Overdue pending SPKL (due date in the past).
     */
    public function overdue(int $daysPastDue = 3): static
    {
        return $this->pending()->state(fn () => [
            'due_date' => Carbon::now('Asia/Jakarta')->subDays($daysPastDue)->toDateString(),
        ]);
    }

    /**
     * File attached, awaiting verification.
     */
    public function attached(?User $actor = null): static
    {
        $now = Carbon::now('Asia/Jakarta');

        return $this->state(fn () => [
            'status' => 'ATTACHED',
            'spkl_number' => 'SPKL-'.fake()->numerify('######'),
            'file_path' => 'spkl/demo/'.fake()->uuid().'.pdf',
            'file_name' => 'SPKL_'.fake()->numerify('######').'.pdf',
            'file_size_bytes' => fake()->numberBetween(50_000, 500_000),
            'mime_type' => 'application/pdf',
            'attached_at' => $now,
            'attached_by_user_id' => $actor?->id ?? User::factory(),
            'due_date' => $now->copy()->addWeekdays(2)->toDateString(),
        ]);
    }

    /**
     * Verified SPKL document.
     */
    public function verified(?User $actor = null): static
    {
        return $this->attached($actor)->state(fn () => [
            'status' => 'VERIFIED',
        ]);
    }
}
