<?php

use App\Models\CapexProject;
use App\Models\Department;
use App\Models\Employee;
use App\Models\MlAnomalyLog;
use App\Models\MlModel;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function ensureApprovalCalendar(string $date = '2026-09-08'): void
{
    $exists = OperationalCalendar::whereDate('calendar_date', $date)->exists();

    if (! $exists) {
        OperationalCalendar::create([
            'calendar_date' => $date,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }
}

/**
 * @param  array<string, mixed>  $submissionAttrs
 * @param  array<string, mixed>  $itemAttrs
 * @param  array{department?: Department, section?: Section, teamLeader?: User, manager?: User, employee?: Employee}  $context
 * @return array{department: Department, section: Section, teamLeader: User, manager: User, employee: Employee, submission: OvertimeSubmission, item: OvertimeItem}
 */
function createApprovalQueueFixture(array $submissionAttrs = [], array $itemAttrs = [], array $context = []): array
{
    ensureApprovalCalendar($submissionAttrs['operational_date'] ?? '2026-09-08');

    $department = $context['department'] ?? Department::factory()->create([
        'code' => 'DEPT_AQ_'.uniqid(),
        'name' => 'Stamping Approval Dept',
        'is_active' => true,
    ]);

    $section = $context['section'] ?? Section::factory()->create([
        'department_id' => $department->id,
        'code' => 'SEC_AQ_'.uniqid(),
        'name' => 'Press Line A',
        'is_active' => true,
    ]);

    $teamLeader = $context['teamLeader'] ?? User::factory()->teamLeader($section->id, $department->id)->create();
    $manager = $context['manager'] ?? User::factory()->manager($department->id)->create();

    $employee = $context['employee'] ?? Employee::factory()->forDepartmentAndSection($department, $section)->create([
        'full_name' => 'Budi Santoso',
        'npk' => 'NPK-'.fake()->unique()->numerify('#####'),
        'hourly_rate' => 35000,
        'is_active' => true,
    ]);

    $date = $submissionAttrs['operational_date'] ?? '2026-09-08';

    $submission = OvertimeSubmission::create(array_merge([
        'submission_code' => 'OT-'.str_replace('-', '', $date).'-AQ-'.uniqid(),
        'submission_date' => $date,
        'operational_date' => $date,
        'day_type' => 'HKN',
        'department_id' => $department->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 4.5,
    ], $submissionAttrs));

    $hoursProduction = (float) ($itemAttrs['hours_production'] ?? 3.0);
    $hoursTpm = (float) ($itemAttrs['hours_tpm'] ?? 1.0);
    $hoursProject = (float) ($itemAttrs['hours_project'] ?? 0.5);
    $hoursOthers = (float) ($itemAttrs['hours_others'] ?? 0.0);
    $totalHours = $hoursProduction + $hoursTpm + $hoursProject + $hoursOthers;
    $rate = 35000.0;

    $item = OvertimeItem::create(array_merge([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => $hoursProduction,
        'hours_tpm' => $hoursTpm,
        'hours_project' => $hoursProject,
        'hours_others' => $hoursOthers,
        'hourly_rate_snapshot' => $rate,
        'total_cost_snapshot' => $totalHours * $rate,
        'status' => 'PENDING',
        'task_description' => 'Press die setup',
    ], $itemAttrs));

    $submission->update(['total_hours_cached' => $totalHours]);

    return compact('department', 'section', 'teamLeader', 'manager', 'employee', 'submission', 'item');
}

test('guest is redirected to login when accessing approval queue', function () {
    $this->get(route('overtime.approvals'))->assertRedirect(route('login'));
});

test('team leader cannot access approval queue', function () {
    $fixture = createApprovalQueueFixture();

    $this->actingAs($fixture['teamLeader'])
        ->get(route('overtime.approvals'))
        ->assertForbidden();
});

test('operator user cannot access approval queue', function () {
    $user = User::factory()->user()->create();

    $this->actingAs($user)
        ->get(route('overtime.approvals'))
        ->assertForbidden();
});

test('manager sees submitted submissions for their department only', function () {
    $fixture = createApprovalQueueFixture([
        'operational_date' => Carbon::now('Asia/Jakarta')->toDateString(),
        'submission_code' => 'OT-OWN-DEPT-001',
    ]);

    $otherDept = Department::factory()->create(['is_active' => true]);
    $otherSection = Section::factory()->create([
        'department_id' => $otherDept->id,
        'is_active' => true,
    ]);
    $otherTl = User::factory()->teamLeader($otherSection->id, $otherDept->id)->create();
    $date = Carbon::now('Asia/Jakarta')->toDateString();
    ensureApprovalCalendar($date);

    OvertimeSubmission::create([
        'submission_code' => 'OT-OTHER-DEPT-001',
        'submission_date' => $date,
        'operational_date' => $date,
        'day_type' => 'HKN',
        'department_id' => $otherDept->id,
        'section_id' => $otherSection->id,
        'submitted_by_user_id' => $otherTl->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 2.0,
    ]);

    $response = $this->actingAs($fixture['manager'])
        ->get(route('overtime.approvals'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('overtime/ApprovalQueue')
        ->has('submissions.data', 1)
        ->where('submissions.data.0.submission_code', 'OT-OWN-DEPT-001')
        ->where('pending_count', 1)
        ->has('available_sections')
        ->has('filters')
    );
});

test('admin sees plant-wide submissions and can filter by department', function () {
    $fixtureA = createApprovalQueueFixture([
        'operational_date' => Carbon::now('Asia/Jakarta')->toDateString(),
        'submission_code' => 'OT-ADMIN-A-001',
    ]);
    $fixtureB = createApprovalQueueFixture([
        'operational_date' => Carbon::now('Asia/Jakarta')->toDateString(),
        'submission_code' => 'OT-ADMIN-B-001',
    ]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('overtime.approvals'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('overtime/ApprovalQueue')
            ->has('submissions.data', 2)
            ->has('available_departments')
            ->where('pending_count', 2)
        );

    $this->actingAs($admin)
        ->get(route('overtime.approvals', [
            'department_id' => $fixtureA['department']->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('submissions.data', 1)
            ->where('submissions.data.0.submission_code', 'OT-ADMIN-A-001')
            ->where('filters.department_id', (string) $fixtureA['department']->id)
        );

    expect($fixtureB['submission']->exists)->toBeTrue();
});

test('default status filter shows submitted and status tab can show partially approved', function () {
    $date = Carbon::now('Asia/Jakarta')->toDateString();
    $fixture = createApprovalQueueFixture([
        'operational_date' => $date,
        'status' => 'SUBMITTED',
        'submission_code' => 'OT-STATUS-SUB',
    ]);

    createApprovalQueueFixture([
        'operational_date' => $date,
        'status' => 'PARTIALLY_APPROVED',
        'submission_code' => 'OT-STATUS-PARTIAL',
        'total_hours_cached' => 3.0,
    ], [], [
        'department' => $fixture['department'],
        'section' => $fixture['section'],
        'teamLeader' => $fixture['teamLeader'],
        'manager' => $fixture['manager'],
        'employee' => $fixture['employee'],
    ]);

    createApprovalQueueFixture([
        'operational_date' => $date,
        'status' => 'APPROVED',
        'submission_code' => 'OT-STATUS-APPROVED',
        'total_hours_cached' => 1.0,
    ], [], [
        'department' => $fixture['department'],
        'section' => $fixture['section'],
        'teamLeader' => $fixture['teamLeader'],
        'manager' => $fixture['manager'],
        'employee' => $fixture['employee'],
    ]);

    $this->actingAs($fixture['manager'])
        ->get(route('overtime.approvals'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('submissions.data', 1)
            ->where('submissions.data.0.submission_code', 'OT-STATUS-SUB')
            ->where('pending_count', 2)
        );

    $this->actingAs($fixture['manager'])
        ->get(route('overtime.approvals', ['status' => 'PARTIALLY_APPROVED']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('submissions.data', 1)
            ->where('submissions.data.0.submission_code', 'OT-STATUS-PARTIAL')
        );

    $this->actingAs($fixture['manager'])
        ->get(route('overtime.approvals', ['status' => 'ALL']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('submissions.data', 3)
        );
});

test('queue supports section date range spkl status and sorting filters', function () {
    $today = Carbon::now('Asia/Jakarta');
    $todayStr = $today->toDateString();
    $oldDate = $today->copy()->subDays(10)->toDateString();
    ensureApprovalCalendar($todayStr);
    ensureApprovalCalendar($oldDate);

    $fixture = createApprovalQueueFixture([
        'operational_date' => $todayStr,
        'submission_code' => 'OT-FILTER-TODAY',
        'total_hours_cached' => 10.0,
    ]);

    $fixture['submission']->spklDocument()->create([
        'status' => 'PENDING',
        'due_date' => $today->copy()->subDays(1)->toDateString(),
    ]);

    $sectionB = Section::factory()->create([
        'department_id' => $fixture['department']->id,
        'code' => 'SEC_AQ_B',
        'name' => 'Assembly Line B',
        'is_active' => true,
    ]);

    $oldSubmission = OvertimeSubmission::create([
        'submission_code' => 'OT-FILTER-OLD',
        'submission_date' => $oldDate,
        'operational_date' => $oldDate,
        'day_type' => 'HKN',
        'department_id' => $fixture['department']->id,
        'section_id' => $sectionB->id,
        'submitted_by_user_id' => $fixture['teamLeader']->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 2.0,
    ]);

    $oldSubmission->spklDocument()->create([
        'status' => 'ATTACHED',
        'due_date' => $todayStr,
        'attached_at' => now(),
    ]);

    $this->actingAs($fixture['manager'])
        ->get(route('overtime.approvals', [
            'section_id' => $fixture['section']->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('submissions.data', 1)
            ->where('submissions.data.0.submission_code', 'OT-FILTER-TODAY')
        );

    $this->actingAs($fixture['manager'])
        ->get(route('overtime.approvals', [
            'spkl_status' => 'OVERDUE',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('submissions.data', 1)
            ->where('submissions.data.0.submission_code', 'OT-FILTER-TODAY')
        );

    $this->actingAs($fixture['manager'])
        ->get(route('overtime.approvals', [
            'date_from' => $oldDate,
            'date_to' => $oldDate,
            'status' => 'ALL',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('submissions.data', 1)
            ->where('submissions.data.0.submission_code', 'OT-FILTER-OLD')
        );

    $this->actingAs($fixture['manager'])
        ->get(route('overtime.approvals', [
            'date_from' => $oldDate,
            'date_to' => $todayStr,
            'sort' => 'total_hours',
            'direction' => 'asc',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('submissions.data', 2)
            ->where('submissions.data.0.submission_code', 'OT-FILTER-OLD')
            ->where('submissions.data.1.submission_code', 'OT-FILTER-TODAY')
        );
});

test('queue payload includes item aggregates cost anomaly count and expandable items', function () {
    $date = Carbon::now('Asia/Jakarta')->toDateString();
    $fixture = createApprovalQueueFixture([
        'operational_date' => $date,
        'submission_code' => 'OT-AGG-001',
    ]);

    $capex = CapexProject::create([
        'project_code' => 'CAPEX-AQ-001',
        'asset_code' => 'AST-001',
        'name' => 'Line Automation',
        'department_id' => $fixture['department']->id,
        'allocated_labor_hours' => 100,
        'allocated_labor_budget_idr' => 5000000,
        'physical_progress_pct' => 10,
        'status' => 'ACTIVE',
        'start_date' => $date,
        'target_end_date' => Carbon::parse($date)->addMonths(3)->toDateString(),
    ]);

    // Link CapEx without mutating immutable cost snapshot fields
    OvertimeItem::query()->whereKey($fixture['item']->id)->update([
        'capex_project_id' => $capex->id,
    ]);
    $fixture['item']->refresh();

    $mlModel = MlModel::create([
        'model_key' => 'XGB_ANOMALY_AQ_'.uniqid(),
        'model_type' => 'ANOMALY_DETECTION',
        'version' => '1.0.0',
        'algorithm_name' => 'IsolationForest',
        'hyperparameters' => ['contamination' => 0.05],
        'metrics' => ['precision' => 0.9],
        'is_active' => true,
        'trained_at' => now(),
    ]);

    MlAnomalyLog::create([
        'overtime_item_id' => $fixture['item']->id,
        'ml_model_id' => $mlModel->id,
        'anomaly_score' => 0.91,
        'anomaly_reasons' => ['Hours 2x historical average'],
        'is_dismissed' => false,
        'created_at' => now(),
    ]);

    MlAnomalyLog::create([
        'overtime_item_id' => $fixture['item']->id,
        'ml_model_id' => $mlModel->id,
        'anomaly_score' => 0.5,
        'anomaly_reasons' => ['Dismissed noise'],
        'is_dismissed' => true,
        'dismissed_at' => now(),
        'created_at' => now(),
    ]);

    $this->actingAs($fixture['manager'])
        ->get(route('overtime.approvals'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('submissions.data', 1)
            ->where('submissions.data.0.submission_code', 'OT-AGG-001')
            ->where('submissions.data.0.items_count', 1)
            ->where('submissions.data.0.anomaly_count', 1)
            ->has('submissions.data.0.items', 1)
            ->where('submissions.data.0.items.0.capex_project.project_code', 'CAPEX-AQ-001')
            ->where('submissions.data.0.items.0.employee.full_name', 'Budi Santoso')
            ->has('submissions.data.0.total_cost_cached')
        );
});

test('pending approvals count is shared for manager and admin sidebar badge', function () {
    $date = Carbon::now('Asia/Jakarta')->toDateString();
    $fixture = createApprovalQueueFixture([
        'operational_date' => $date,
        'status' => 'SUBMITTED',
    ]);

    createApprovalQueueFixture([
        'operational_date' => $date,
        'status' => 'PARTIALLY_APPROVED',
    ], [], [
        'department' => $fixture['department'],
        'section' => $fixture['section'],
        'teamLeader' => $fixture['teamLeader'],
        'manager' => $fixture['manager'],
        'employee' => $fixture['employee'],
    ]);

    $this->actingAs($fixture['manager'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pending_approvals_count', 2)
        );

    $this->actingAs($fixture['teamLeader'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pending_approvals_count', 0)
        );
});

test('approval queue paginates twenty records per page', function () {
    $date = Carbon::now('Asia/Jakarta')->toDateString();
    ensureApprovalCalendar($date);

    $department = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create([
        'department_id' => $department->id,
        'is_active' => true,
    ]);
    $teamLeader = User::factory()->teamLeader($section->id, $department->id)->create();
    $manager = User::factory()->manager($department->id)->create();

    for ($i = 1; $i <= 21; $i++) {
        OvertimeSubmission::create([
            'submission_code' => sprintf('OT-PAGE-%03d', $i),
            'submission_date' => $date,
            'operational_date' => $date,
            'day_type' => 'HKN',
            'department_id' => $department->id,
            'section_id' => $section->id,
            'submitted_by_user_id' => $teamLeader->id,
            'status' => 'SUBMITTED',
            'total_hours_cached' => 1.0,
        ]);
    }

    $this->actingAs($manager)
        ->get(route('overtime.approvals'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('submissions.data', 20)
            ->where('submissions.per_page', 20)
            ->where('submissions.total', 21)
            ->where('submissions.last_page', 2)
        );
});
