<?php

use App\Models\CapexProject;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ExportLog;
use App\Models\MlAnomalyLog;
use App\Models\MlModel;
use App\Models\MlPrediction;
use App\Models\MonthlyBurnSnapshot;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeItemAudit;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\SpklDocument;
use App\Models\User;
use App\Models\UserAudit;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('overtime submission factory creates calendar, items, and spkl', function () {
    $department = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $department->id]);
    $user = User::factory()->teamLeader($section->id, $department->id)->create();

    $submission = OvertimeSubmission::factory()
        ->forSection($section)
        ->onDate('2026-09-10')
        ->state(['submitted_by_user_id' => $user->id])
        ->withItems(2, 'PENDING')
        ->withSpkl('PENDING')
        ->create();

    expect($submission->submission_code)->toStartWith('OT-')
        ->and(OperationalCalendar::whereDate('calendar_date', '2026-09-10')->exists())->toBeTrue()
        ->and($submission->items)->toHaveCount(2)
        ->and($submission->spklDocument)->not->toBeNull()
        ->and($submission->spklDocument->status)->toBe('PENDING')
        ->and((float) $submission->fresh()->total_hours_cached)->toBeGreaterThan(0);
});

test('overtime item factory supports approved and capex states', function () {
    $department = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $department->id]);
    $employee = Employee::factory()->forDepartmentAndSection($department, $section)->create([
        'hourly_rate' => 45000,
    ]);
    $project = CapexProject::factory()->active()->create(['department_id' => $department->id]);
    $reviewer = User::factory()->manager($department->id)->create();

    $submission = OvertimeSubmission::factory()
        ->forSection($section)
        ->onDate('2026-09-11')
        ->create();

    $item = OvertimeItem::factory()
        ->forEmployee($employee)
        ->withCapex($project, 2.5)
        ->approved($reviewer)
        ->create(['overtime_submission_id' => $submission->id])
        ->fresh();

    expect($item->status)->toBe('APPROVED')
        ->and($item->capex_project_id)->toBe($project->id)
        ->and((float) $item->hours_project)->toBe(2.5)
        ->and((float) $item->total_hours)->toBe(2.5)
        ->and($item->reviewed_by_user_id)->toBe($reviewer->id);
});

test('supporting factories create valid domain records', function () {
    expect(OperationalCalendar::factory()->forDate('2026-09-12')->create()->day_type)->toBeIn(['HKN', 'HLR'])
        ->and(MonthlyBurnSnapshot::factory()->warning()->create()->burn_zone)->toBe('ZONE_3_WARNING')
        ->and(SpklDocument::factory()->overdue()->create()->status)->toBe('PENDING')
        ->and(OvertimeItemAudit::factory()->approvedAction()->create()->action)->toBe('APPROVED')
        ->and(UserAudit::factory()->create()->action)->not->toBeEmpty()
        ->and(ExportLog::factory()->create()->format)->toBeIn(['xlsx', 'csv', 'pdf'])
        ->and(MlModel::factory()->anomalyDetection()->active()->create()->is_active)->toBeTrue()
        ->and(MlPrediction::factory()->highRisk()->create()->risk_level)->toBe('HIGH')
        ->and(MlAnomalyLog::factory()->highAnomaly()->create()->anomaly_score)->toBeGreaterThanOrEqual(0.8);
});

test('database seeder generates master and dummy transactional data', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Department::count())->toBeGreaterThan(0)
        ->and(Section::count())->toBeGreaterThan(0)
        ->and(Employee::count())->toBeGreaterThan(0)
        ->and(User::where('email', 'admin@factory.com')->exists())->toBeTrue()
        ->and(OperationalCalendar::count())->toBeGreaterThan(300)
        ->and(CapexProject::count())->toBeGreaterThan(0)
        ->and(OvertimeSubmission::count())->toBeGreaterThan(0)
        ->and(OvertimeItem::count())->toBeGreaterThan(0)
        ->and(SpklDocument::count())->toBeGreaterThan(0)
        ->and(MonthlyBurnSnapshot::count())->toBeGreaterThan(0)
        ->and(MlModel::count())->toBeGreaterThan(0);
});
