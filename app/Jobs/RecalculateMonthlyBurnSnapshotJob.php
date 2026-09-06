<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalculateMonthlyBurnSnapshotJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var list<int>
     */
    public array $backoff = [30, 120, 300];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?int $sectionId = null,
        public ?int $fiscalYear = null,
        public ?int $fiscalMonth = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Scaffolded in Epic E01-06.
        // Asynchronous snapshot rollup and threshold evaluations will be implemented in Epic-04 & Epic-05.
    }
}
