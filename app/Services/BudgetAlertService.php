<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\MonthlyBurnSnapshot;
use App\Models\User;
use App\Notifications\BudgetThresholdAlert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class BudgetAlertService
{
    public function __construct(
        protected PolicyThresholdService $policyThresholdService,
    ) {}

    /**
     * Evaluate whether a monthly burn snapshot crossed warning or danger thresholds
     * and dispatch alerts to eligible managers and admins with timestamp deduplication.
     */
    public function evaluateAndNotify(MonthlyBurnSnapshot $snapshot): void
    {
        // Skip unconfigured budgets where planned hours is zero or negative
        if ((float) $snapshot->planned_budget_hours <= 0) {
            return;
        }

        $threshold = $this->policyThresholdService->getForDepartment($snapshot->department_id);
        $warningPct = (float) $threshold->burn_warning_pct;
        $dangerPct = (float) $threshold->burn_danger_pct;
        $burnIndex = (float) $snapshot->burn_index_pct;

        $now = Carbon::now('Asia/Jakarta');

        // Check danger threshold (critical deficit) first
        if ($burnIndex >= $dangerPct && $snapshot->danger_at === null) {
            $this->dispatchAlert($snapshot, 'danger', $dangerPct);

            $snapshot->danger_at = $now;
            if ($snapshot->warned_at === null) {
                $snapshot->warned_at = $now;
            }
            $snapshot->save();

            return;
        }

        // Check warning threshold (approaching budget ceiling)
        if ($burnIndex >= $warningPct && $snapshot->warned_at === null) {
            $this->dispatchAlert($snapshot, 'warning', $warningPct);

            $snapshot->warned_at = $now;
            $snapshot->save();
        }
    }

    /**
     * Dispatch the threshold notification to all eligible recipients.
     *
     * @param  'warning'|'danger'  $alertLevel
     */
    protected function dispatchAlert(MonthlyBurnSnapshot $snapshot, string $alertLevel, float $thresholdPct): void
    {
        $recipients = $this->getEligibleRecipients($snapshot->department_id);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new BudgetThresholdAlert($snapshot, $alertLevel, $thresholdPct));
    }

    /**
     * Retrieve active Administrators and Department Managers eligible for budget alerts,
     * filtered by their notification preference.
     *
     * @return Collection<int, User>
     */
    public function getEligibleRecipients(int $departmentId): Collection
    {
        $admins = User::query()
            ->where('role', UserRole::Admin)
            ->where('is_active', true)
            ->get();

        $managers = User::query()
            ->where('role', UserRole::Manager)
            ->where('department_id', $departmentId)
            ->where('is_active', true)
            ->get();

        return $admins->concat($managers)
            ->unique('id')
            ->values()
            ->filter(function (User $user): bool {
                $prefs = $user->getEffectivePreferences();

                return (bool) ($prefs['budget_threshold_alert'] ?? true);
            });
    }
}
