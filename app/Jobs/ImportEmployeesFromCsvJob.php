<?php

namespace App\Jobs;

use App\Services\EmployeeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImportEmployeesFromCsvJob implements ShouldQueue
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
    public array $backoff = [10, 30, 60];

    /**
     * Create a new job instance.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(
        public array $rows,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(EmployeeService $employeeService): void
    {
        $employeeService->importRows($this->rows);
    }
}
