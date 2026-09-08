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

test('manager can open approval queue from sidebar and inspect expandable row', function () {
    $today = Carbon::now('Asia/Jakarta')->toDateString();

    if (! OperationalCalendar::whereDate('calendar_date', $today)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $today,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }

    $dept = Department::factory()->create([
        'code' => 'DEPT_BRW_AQ',
        'name' => 'Stamping Browser Dept',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_BRW_PRESS',
        'name' => 'Press Browser Line',
        'is_active' => true,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create([
        'name' => 'Agus Team Leader',
    ]);

    User::factory()->manager($dept->id)->create([
        'name' => 'Manager Approval Browser',
        'email' => 'mgr.approval@factory.com',
        'password' => 'password',
    ]);

    $employee = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'full_name' => 'Budi Operator',
        'npk' => 'EMP-AQ-9001',
        'hourly_rate' => 35000,
        'is_active' => true,
    ]);

    $capex = CapexProject::create([
        'project_code' => 'CAPEX-BRW-042',
        'asset_code' => 'AST-BRW',
        'name' => 'Welding Robot Install',
        'department_id' => $dept->id,
        'allocated_labor_hours' => 200,
        'allocated_labor_budget_idr' => 10000000,
        'physical_progress_pct' => 20,
        'status' => 'ACTIVE',
        'start_date' => $today,
        'target_end_date' => Carbon::parse($today)->addMonths(2)->toDateString(),
    ]);

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-BRW-AQ-001',
        'submission_date' => $today,
        'operational_date' => $today,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 5.0,
    ]);

    $submission->spklDocument()->create([
        'status' => 'PENDING',
        'due_date' => Carbon::parse($today)->addDay()->toDateString(),
    ]);

    $item = OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'capex_project_id' => $capex->id,
        'hours_production' => 2.0,
        'hours_tpm' => 1.0,
        'hours_project' => 2.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 35000,
        'total_cost_snapshot' => 5.0 * 35000,
        'status' => 'PENDING',
        'task_description' => 'Robot commissioning',
    ]);

    $mlModel = MlModel::create([
        'model_key' => 'XGB_BRW_AQ_'.uniqid(),
        'model_type' => 'ANOMALY_DETECTION',
        'version' => '1.0.0',
        'algorithm_name' => 'IsolationForest',
        'hyperparameters' => [],
        'metrics' => [],
        'is_active' => true,
        'trained_at' => now(),
    ]);

    MlAnomalyLog::create([
        'overtime_item_id' => $item->id,
        'ml_model_id' => $mlModel->id,
        'anomaly_score' => 0.88,
        'anomaly_reasons' => ['Jam lembur 2x rata-rata historis'],
        'is_dismissed' => false,
        'created_at' => now(),
    ]);

    $page = visit('/login')
        ->fill('email', 'mgr.approval@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->assertSee('OT-CapEx System')
        ->click('[data-test="nav-overtime-approvals"]')
        ->assertPathIs('/overtime/approvals')
        ->assertSee('OT-BRW-AQ-001')
        ->assertSee('Press Browser Line')
        ->assertSee('Agus Team Leader')
        ->assertSee('Stamping Browser Dept')
        ->assertSee('5.0')
        ->assertNoJavaScriptErrors();

    // Pending badge + anomaly badge should be present on the queue page
    $page->assertSee('1')
        ->assertPresent('[data-test="pending-count-pill"]')
        ->assertPresent('[data-test="ml-anomaly-badge"]')
        ->assertPresent('[data-test="spkl-status-badge"]')
        ->assertPresent('[data-test="approval-live-clock"]');

    $page->click('[data-test="expand-row-'.$submission->id.'"]')
        ->assertSee('Budi Operator')
        ->assertSee('EMP-AQ-9001')
        ->assertSee('CAPEX-BRW-042')
        ->assertPresent('[data-test="inline-row-summary"]')
        ->assertNoJavaScriptErrors();
});

test('team leader does not see approval queue sidebar item', function () {
    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'is_active' => true,
    ]);

    User::factory()->teamLeader($section->id, $dept->id)->create([
        'email' => 'tl.noapproval@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'tl.noapproval@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->assertMissing('[data-test="nav-overtime-approvals"]')
        ->assertNoJavaScriptErrors();
});

test('manager can switch status tabs and see empty state for unmatched filter', function () {
    $today = Carbon::now('Asia/Jakarta')->toDateString();

    if (! OperationalCalendar::whereDate('calendar_date', $today)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $today,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }

    $dept = Department::factory()->create([
        'code' => 'DEPT_BRW_TAB',
        'name' => 'Filter Tab Dept',
        'is_active' => true,
    ]);
    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'is_active' => true,
    ]);
    $tl = User::factory()->teamLeader($section->id, $dept->id)->create();

    User::factory()->manager($dept->id)->create([
        'email' => 'mgr.tabs@factory.com',
        'password' => 'password',
    ]);

    OvertimeSubmission::create([
        'submission_code' => 'OT-BRW-TAB-SUB',
        'submission_date' => $today,
        'operational_date' => $today,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $tl->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 3.0,
    ]);

    visit('/login')
        ->fill('email', 'mgr.tabs@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-overtime-approvals"]')
        ->assertPathIs('/overtime/approvals')
        ->assertSee('OT-BRW-TAB-SUB')
        ->click('[data-test="status-tab-partially_approved"]')
        ->assertPresent('[data-test="approval-empty-state"]')
        ->click('[data-test="status-tab-submitted"]')
        ->assertSee('OT-BRW-TAB-SUB')
        ->assertNoJavaScriptErrors();
});
