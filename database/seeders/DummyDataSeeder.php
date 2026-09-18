<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\CapexProject;
use App\Models\Employee;
use App\Models\ExportLog;
use App\Models\MlAnomalyLog;
use App\Models\MlModel;
use App\Models\MlPrediction;
use App\Models\MonthlyBurnSnapshot;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\OvertimeItemAudit;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use App\Models\UserAudit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Generates realistic transactional dummy data on top of master seeders.
 *
 * Prerequisites (via DatabaseSeeder): departments, sections, employees, users,
 * operational calendar, policy thresholds, overtime budgets.
 */
class DummyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(CapexProjectSeeder::class);

        $now = Carbon::now('Asia/Jakarta');
        $fiscalYear = (int) $now->year;
        $fiscalMonth = (int) $now->month;

        $sections = Section::query()
            ->with('department')
            ->where('is_active', true)
            ->get();

        if ($sections->isEmpty()) {
            $this->command?->warn('DummyDataSeeder: no sections found — skip transactional seeds.');

            return;
        }

        $teamLeaders = User::query()
            ->where('role', UserRole::TeamLeader)
            ->where('is_active', true)
            ->get()
            ->keyBy('section_id');

        $managers = User::query()
            ->where('role', UserRole::Manager)
            ->where('is_active', true)
            ->get()
            ->keyBy('department_id');

        $admin = User::query()->where('role', UserRole::Admin)->first()
            ?? User::factory()->admin()->create(['email' => 'admin@factory.com']);

        $capexByDept = CapexProject::query()
            ->where('status', 'ACTIVE')
            ->get()
            ->groupBy('department_id');

        $anomalyModel = MlModel::factory()->anomalyDetection()->active()->create([
            'model_key' => 'anomaly_detection_plant_v1',
            'version' => '1.0.0',
        ]);

        $burnModel = MlModel::factory()->active()->create([
            'model_key' => 'burn_trajectory_plant_v1',
            'model_type' => 'BURN_TRAJECTORY',
            'algorithm_name' => 'Prophet',
            'version' => '1.0.0',
        ]);

        $workdays = $this->recentWorkdays($now, 10);
        $createdSubmissions = 0;

        foreach ($sections as $sectionIndex => $section) {
            $submitter = $teamLeaders->get($section->id)
                ?? User::factory()->teamLeader($section->id, $section->department_id)->create();

            $reviewer = $managers->get($section->department_id) ?? $admin;

            $employees = Employee::query()
                ->where('section_id', $section->id)
                ->where('is_active', true)
                ->limit(6)
                ->get();

            if ($employees->isEmpty()) {
                $employees = Employee::factory()
                    ->count(4)
                    ->forDepartmentAndSection($section->department_id, $section->id)
                    ->create();
            }

            // Seed 2–3 submissions per section across recent workdays
            $datesForSection = $workdays->take(3)->values();

            foreach ($datesForSection as $dateIndex => $date) {
                $status = match ($dateIndex) {
                    0 => 'APPROVED',
                    1 => 'SUBMITTED',
                    default => 'PARTIALLY_APPROVED',
                };

                $itemStatus = match ($status) {
                    'APPROVED' => 'APPROVED',
                    'PARTIALLY_APPROVED' => 'PENDING',
                    default => 'PENDING',
                };

                $spklStatus = match ($status) {
                    'APPROVED' => 'VERIFIED',
                    'PARTIALLY_APPROVED' => 'ATTACHED',
                    default => ($dateIndex === 1 && $sectionIndex % 4 === 0) ? 'OVERDUE' : 'PENDING',
                };

                $submission = OvertimeSubmission::factory()
                    ->forSection($section)
                    ->onDate($date)
                    ->state([
                        'submitted_by_user_id' => $submitter->id,
                        'status' => $status,
                        'submission_notes' => "Dummy OT {$section->code} @ {$date}",
                    ])
                    ->withSpkl($spklStatus)
                    ->create();

                $picked = $employees->random(min(3, $employees->count()));
                $totalHours = 0.0;

                foreach ($picked as $empIndex => $employee) {
                    $useCapex = $empIndex === 0
                        && $capexByDept->has($section->department_id)
                        && $status !== 'DRAFT';

                    $factory = OvertimeItem::factory()
                        ->forEmployee($employee)
                        ->state(['overtime_submission_id' => $submission->id]);

                    if ($useCapex) {
                        $project = $capexByDept->get($section->department_id)->first();
                        $factory = $factory->withCapex($project, 2.0);
                    }

                    if ($status === 'APPROVED') {
                        $factory = $factory->approved($reviewer);
                    } elseif ($status === 'PARTIALLY_APPROVED' && $empIndex === 0) {
                        $factory = $factory->approved($reviewer);
                    } elseif ($status === 'PARTIALLY_APPROVED' && $empIndex === 1) {
                        $factory = $factory->rejected($reviewer);
                    } else {
                        $factory = $factory->pending();
                    }

                    // Override status defaults from withCapex chain when needed
                    if ($itemStatus === 'PENDING' && $status === 'SUBMITTED') {
                        $factory = $factory->pending();
                    }

                    $item = $factory->create();
                    $totalHours += (float) $item->hours_production
                        + (float) $item->hours_tpm
                        + (float) $item->hours_project
                        + (float) $item->hours_others;

                    OvertimeItemAudit::factory()
                        ->createdAction()
                        ->create([
                            'overtime_item_id' => $item->id,
                            'actor_user_id' => $submitter->id,
                        ]);

                    if ($item->status === 'APPROVED') {
                        OvertimeItemAudit::factory()
                            ->approvedAction()
                            ->create([
                                'overtime_item_id' => $item->id,
                                'actor_user_id' => $reviewer->id,
                            ]);
                    }

                    if ($item->status === 'REJECTED') {
                        OvertimeItemAudit::factory()
                            ->rejectedAction()
                            ->create([
                                'overtime_item_id' => $item->id,
                                'actor_user_id' => $reviewer->id,
                            ]);
                    }

                    // Sparse anomaly flags on ~15% of items
                    if ($sectionIndex % 5 === 0 && $empIndex === 0) {
                        MlAnomalyLog::factory()
                            ->highAnomaly()
                            ->create([
                                'overtime_item_id' => $item->id,
                                'ml_model_id' => $anomalyModel->id,
                            ]);
                    }
                }

                $submission->update(['total_hours_cached' => round($totalHours, 2)]);
                $createdSubmissions++;
            }

            $this->seedBurnSnapshot($section, $fiscalYear, $fiscalMonth, $sectionIndex);

            MlPrediction::factory()->create([
                'ml_model_id' => $burnModel->id,
                'target_type' => 'section',
                'target_id' => $section->id,
                'prediction_horizon' => 'EOMonth',
                'risk_level' => $sectionIndex % 4 === 0 ? 'HIGH' : 'MEDIUM',
            ]);
        }

        ExportLog::factory()->count(5)->create([
            'actor_user_id' => $admin->id,
        ]);

        UserAudit::factory()->count(3)->create([
            'actor_user_id' => $admin->id,
        ]);

        $this->command?->info("DummyDataSeeder: created {$createdSubmissions} overtime submissions with items, SPKL, burn snapshots, and ML samples.");
    }

    /**
     * @return Collection<int, string>
     */
    protected function recentWorkdays(Carbon $now, int $count)
    {
        $dates = collect();
        $cursor = $now->copy()->subDay();

        while ($dates->count() < $count) {
            if (! $cursor->isWeekend()) {
                $dates->push($cursor->toDateString());
            }
            $cursor->subDay();
        }

        return $dates;
    }

    protected function seedBurnSnapshot(Section $section, int $year, int $month, int $sectionIndex): void
    {
        $budget = OvertimeBudget::query()
            ->where('section_id', $section->id)
            ->where('fiscal_year', $year)
            ->where('fiscal_month', $month)
            ->first();

        $planned = (float) ($budget?->planned_hours ?? 200.00);

        $actual = match ($sectionIndex % 4) {
            0 => round($planned * 1.20, 2),
            1 => round($planned * 1.05, 2),
            2 => round($planned * 0.70, 2),
            default => round($planned * 0.90, 2),
        };

        $capex = round($actual * 0.15, 2);
        $opex = round($actual - $capex, 2);
        $burnIndex = $planned > 0 ? round(($actual / $planned) * 100, 2) : 0.00;
        $velocity = round($actual / 3, 2);

        $factory = MonthlyBurnSnapshot::factory()->forSection($section);

        if ($burnIndex >= 115) {
            $factory = $factory->danger();
        } elseif ($burnIndex >= 100) {
            $factory = $factory->warning();
        }

        $factory->create([
            'fiscal_year' => $year,
            'fiscal_month' => $month,
            'planned_budget_hours' => $planned,
            'cumulative_actual_hours' => $actual,
            'cumulative_opex_hours' => $opex,
            'cumulative_capex_hours' => $capex,
            'burn_index_pct' => $burnIndex,
            'burn_velocity' => $velocity,
        ]);
    }
}
