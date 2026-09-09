<?php

namespace App\Jobs;

use App\Models\MonthlyBurnSnapshot;
use App\Services\Analytics\MonthlySnapshotService;
use App\Services\BudgetAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

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
    public function handle(
        ?MonthlySnapshotService $snapshotService = null,
        ?BudgetAlertService $budgetAlertService = null,
    ): void {
        $snapshotService ??= app(MonthlySnapshotService::class);
        $budgetAlertService ??= app(BudgetAlertService::class);

        $now = Carbon::now('Asia/Jakarta');
        $year = $this->fiscalYear ?? (int) $now->format('Y');
        $month = $this->fiscalMonth ?? (int) $now->format('n');

        if ($this->sectionId !== null) {
            $snapshot = $snapshotService->recalculate($this->sectionId, $year, $month);
            if ($snapshot !== null) {
                $budgetAlertService->evaluateAndNotify($snapshot);
            }
        } else {
            $snapshotService->recalculateAll($year, $month);
            $snapshots = MonthlyBurnSnapshot::query()
                ->where('fiscal_year', $year)
                ->where('fiscal_month', $month)
                ->get();

            foreach ($snapshots as $snapshot) {
                $budgetAlertService->evaluateAndNotify($snapshot);
            }
        }
    }
}
