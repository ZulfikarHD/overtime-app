<?php

use App\Models\CapexProject;
use App\Models\Department;
use App\Models\Employee;
use App\Models\MlAnomalyLog;
use App\Models\MlModel;
use App\Models\MlPrediction;
use App\Models\MonthlyBurnSnapshot;
use App\Models\OperationalCalendar;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\OvertimeItemAudit;
use App\Models\OvertimeSubmission;
use App\Models\PolicyThreshold;
use App\Models\Section;
use App\Models\SpklDocument;
use App\Models\User;
use Carbon\CarbonInterface;

test('all 15 models can be created with fillable attributes and persisted', function () {
    $user = User::factory()->create();

    $department = Department::create([
        'code' => 'PROD',
        'name' => 'Production Department',
        'cost_center_code' => 'CC-PROD-001',
        'default_hourly_rate' => 50000.00,
        'is_active' => true,
    ]);
    expect($department->exists)->toBeTrue()
        ->and($department->id)->toBeGreaterThan(0);

    $section = Section::create([
        'department_id' => $department->id,
        'code' => 'ASSY_LINE_1',
        'name' => 'Assembly Line 1',
        'is_active' => true,
    ]);
    expect($section->exists)->toBeTrue();

    $employee = Employee::create([
        'npk' => 'EMP-1001',
        'department_id' => $department->id,
        'section_id' => $section->id,
        'full_name' => 'Budi Santoso',
        'job_position' => 'Assembly Operator',
        'hourly_rate' => 52000.00,
        'is_active' => true,
    ]);
    expect($employee->exists)->toBeTrue();

    $calendar = OperationalCalendar::create([
        'calendar_date' => '2026-09-06',
        'day_type' => 'HKN',
        'is_holiday' => false,
        'holiday_name' => null,
        'description' => 'Normal Workday',
        'created_at' => now(),
    ]);
    expect($calendar->exists)->toBeTrue()
        ->and($calendar->calendar_date->format('Y-m-d'))->toBe('2026-09-06');

    $threshold = PolicyThreshold::create([
        'department_id' => $department->id,
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
        'spkl_grace_period_days' => 2,
        'burn_warning_pct' => 100.00,
        'burn_danger_pct' => 115.00,
    ]);
    expect($threshold->exists)->toBeTrue();

    $capex = CapexProject::create([
        'project_code' => 'CPX-2026-001',
        'asset_code' => 'AST-9901',
        'name' => 'Line Automation',
        'department_id' => $department->id,
        'allocated_labor_hours' => 500.00,
        'allocated_labor_budget_idr' => 25000000.00,
        'physical_progress_pct' => 35.50,
        'status' => 'ACTIVE',
        'start_date' => '2026-01-01',
        'target_end_date' => '2026-12-31',
    ]);
    expect($capex->exists)->toBeTrue();

    $budget = OvertimeBudget::create([
        'department_id' => $department->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_hours' => 200.00,
        'planned_cost_idr' => 10400000.00,
        'week1_planned_hours' => 40.00,
        'week2_planned_hours' => 40.00,
        'week3_planned_hours' => 40.00,
        'week4_planned_hours' => 40.00,
        'week5_planned_hours' => 40.00,
    ]);
    expect($budget->exists)->toBeTrue();

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-20260906-001',
        'submission_date' => '2026-09-06',
        'operational_date' => $calendar->calendar_date,
        'day_type' => 'HKN',
        'department_id' => $department->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $user->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 4.00,
        'submission_notes' => 'Urgent shift extension',
    ]);
    expect($submission->exists)->toBeTrue();

    $spkl = SpklDocument::create([
        'overtime_submission_id' => $submission->id,
        'spkl_number' => 'SPKL-2026-09-001',
        'file_path' => 'spkl/2026/09/doc.pdf',
        'file_name' => 'doc.pdf',
        'file_size_bytes' => 102400,
        'mime_type' => 'application/pdf',
        'status' => 'ATTACHED',
        'due_date' => '2026-09-08',
        'attached_at' => now(),
        'attached_by_user_id' => $user->id,
    ]);
    expect($spkl->exists)->toBeTrue();

    $item = OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'capex_project_id' => $capex->id,
        'hours_production' => 2.00,
        'hours_tpm' => 1.00,
        'hours_project' => 1.00,
        'hours_others' => 0.00,
        'hourly_rate_snapshot' => 52000.00,
        'total_cost_snapshot' => 208000.00,
        'rca_category' => 'MACHINE_BREAKDOWN',
        'rca_notes' => 'Conveyor belt jam',
        'task_description' => 'Replace conveyor belt',
        'status' => 'PENDING',
        'lock_version' => 1,
    ]);
    expect($item->exists)->toBeTrue();

    $audit = OvertimeItemAudit::create([
        'overtime_item_id' => $item->id,
        'action' => 'SUBMITTED',
        'actor_user_id' => $user->id,
        'previous_state' => null,
        'new_state' => ['status' => 'PENDING', 'hours' => 4.0],
        'notes' => 'Initial submission',
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);
    expect($audit->exists)->toBeTrue();

    $snapshot = MonthlyBurnSnapshot::create([
        'department_id' => $department->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_budget_hours' => 200.00,
        'cumulative_actual_hours' => 150.00,
        'cumulative_opex_hours' => 110.00,
        'cumulative_capex_hours' => 40.00,
        'burn_index_pct' => 75.00,
        'burn_velocity' => 1.05,
        'burn_zone' => 'ZONE_1_EXCELLENT',
        'last_recalculated_at' => now(),
    ]);
    expect($snapshot->exists)->toBeTrue();

    $mlModel = MlModel::create([
        'model_key' => 'XGB_ANOMALY_V1',
        'model_type' => 'ANOMALY_DETECTION',
        'version' => '1.0.0',
        'algorithm_name' => 'IsolationForest',
        'hyperparameters' => ['contamination' => 0.05],
        'metrics' => ['precision' => 0.92, 'recall' => 0.88],
        'is_active' => true,
        'trained_at' => now(),
    ]);
    expect($mlModel->exists)->toBeTrue();

    $prediction = MlPrediction::create([
        'ml_model_id' => $mlModel->id,
        'target_type' => 'SECTION',
        'target_id' => $section->id,
        'prediction_horizon' => 'MONTH_END',
        'predicted_value' => 210.50,
        'confidence_interval_lower' => 195.00,
        'confidence_interval_upper' => 225.00,
        'risk_score' => 0.8250,
        'risk_level' => 'HIGH',
        'feature_impact_json' => ['historical_drift' => 0.45],
        'fallback_used' => false,
        'created_at' => now(),
    ]);
    expect($prediction->exists)->toBeTrue();

    $anomaly = MlAnomalyLog::create([
        'overtime_item_id' => $item->id,
        'ml_model_id' => $mlModel->id,
        'anomaly_score' => 0.8750,
        'anomaly_reasons' => ['High hours variance for shift', 'Repeated breakdown code'],
        'is_dismissed' => false,
        'created_at' => now(),
    ]);
    expect($anomaly->exists)->toBeTrue();
});

test('attribute casting behaves correctly across all models', function () {
    $user = User::factory()->create();

    $department = Department::create([
        'code' => 'ENG',
        'name' => 'Engineering',
        'cost_center_code' => 'CC-ENG-001',
        'default_hourly_rate' => 60000.5,
        'is_active' => 1,
    ]);
    expect($department->default_hourly_rate)->toBe('60000.50')
        ->and($department->is_active)->toBeTrue();

    $section = Section::create([
        'department_id' => $department->id,
        'code' => 'ENG_TOOLING',
        'name' => 'Tooling Section',
        'is_active' => 0,
    ]);
    expect($section->is_active)->toBeFalse();

    $employee = Employee::create([
        'npk' => 'EMP-2001',
        'department_id' => $department->id,
        'section_id' => $section->id,
        'full_name' => 'Siti Aminah',
        'job_position' => 'Engineer',
        'hourly_rate' => 65000,
        'is_active' => true,
    ]);
    expect($employee->hourly_rate)->toBe('65000.00');

    $calendar = OperationalCalendar::create([
        'calendar_date' => '2026-09-07',
        'day_type' => 'HKN',
        'is_holiday' => false,
        'created_at' => now(),
    ]);
    expect($calendar->calendar_date)->toBeInstanceOf(CarbonInterface::class)
        ->and($calendar->calendar_date->format('Y-m-d'))->toBe('2026-09-07')
        ->and($calendar->is_holiday)->toBeFalse();

    $threshold = PolicyThreshold::create([
        'department_id' => null,
        'weekly_soft_limit_hours' => 20,
        'consecutive_weeks_alert' => '3',
        'spkl_grace_period_days' => '2',
        'burn_warning_pct' => 100,
        'burn_danger_pct' => 115.5,
    ]);
    expect($threshold->weekly_soft_limit_hours)->toBe('20.0')
        ->and($threshold->consecutive_weeks_alert)->toBe(3)
        ->and($threshold->spkl_grace_period_days)->toBe(2)
        ->and($threshold->burn_warning_pct)->toBe('100.00')
        ->and($threshold->burn_danger_pct)->toBe('115.50');

    $capex = CapexProject::create([
        'project_code' => 'CPX-2026-002',
        'name' => 'CNC Milling Machine',
        'department_id' => $department->id,
        'allocated_labor_hours' => 120.75,
        'allocated_labor_budget_idr' => 15000000,
        'physical_progress_pct' => 50,
        'status' => 'ACTIVE',
        'start_date' => '2026-02-01',
        'target_end_date' => '2026-08-31',
    ]);
    expect($capex->allocated_labor_hours)->toBe('120.75')
        ->and($capex->allocated_labor_budget_idr)->toBe('15000000.00')
        ->and($capex->physical_progress_pct)->toBe('50.00')
        ->and($capex->start_date)->toBeInstanceOf(CarbonInterface::class)
        ->and($capex->target_end_date)->toBeInstanceOf(CarbonInterface::class);

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-20260907-002',
        'submission_date' => '2026-09-07',
        'operational_date' => '2026-09-07',
        'day_type' => 'HKN',
        'department_id' => $department->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $user->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 3.5,
    ]);
    expect($submission->submission_date)->toBeInstanceOf(CarbonInterface::class)
        ->and($submission->operational_date)->toBeInstanceOf(CarbonInterface::class)
        ->and($submission->total_hours_cached)->toBe('3.50');

    $item = OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 1.5,
        'hours_tpm' => 0.5,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 65000,
        'total_cost_snapshot' => 130000,
        'status' => 'APPROVED',
        'reviewed_by_user_id' => $user->id,
        'reviewed_at' => now(),
        'lock_version' => 1,
    ]);
    $item->refresh();
    expect($item->hours_production)->toBe('1.50')
        ->and($item->hours_tpm)->toBe('0.50')
        ->and($item->total_hours)->toBe('2.00')
        ->and($item->hourly_rate_snapshot)->toBe('65000.00')
        ->and($item->total_cost_snapshot)->toBe('130000.00')
        ->and($item->reviewed_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($item->lock_version)->toBe(1);

    $audit = OvertimeItemAudit::create([
        'overtime_item_id' => $item->id,
        'action' => 'APPROVED',
        'actor_user_id' => $user->id,
        'previous_state' => ['status' => 'PENDING'],
        'new_state' => ['status' => 'APPROVED'],
        'created_at' => now(),
    ]);
    expect($audit->previous_state)->toBeArray()
        ->and($audit->previous_state['status'])->toBe('PENDING')
        ->and($audit->new_state)->toBeArray()
        ->and($audit->new_state['status'])->toBe('APPROVED');

    $mlModel = MlModel::create([
        'model_key' => 'GBM_BURN_V1',
        'model_type' => 'BURN_TRAJECTORY',
        'version' => '1.0.0',
        'algorithm_name' => 'LightGBM',
        'hyperparameters' => ['n_estimators' => 100, 'lr' => 0.05],
        'metrics' => ['mape' => 7.8],
        'is_active' => true,
        'trained_at' => now(),
    ]);
    expect($mlModel->hyperparameters)->toBeArray()
        ->and($mlModel->hyperparameters['n_estimators'])->toBe(100)
        ->and($mlModel->metrics)->toBeArray()
        ->and($mlModel->metrics['mape'])->toBe(7.8)
        ->and($mlModel->is_active)->toBeTrue();

    $prediction = MlPrediction::create([
        'ml_model_id' => $mlModel->id,
        'target_type' => 'DEPARTMENT',
        'target_id' => $department->id,
        'prediction_horizon' => 'MONTH_END',
        'predicted_value' => 520.25,
        'confidence_interval_lower' => 490.10,
        'confidence_interval_upper' => 550.40,
        'risk_score' => 0.1234,
        'risk_level' => 'LOW',
        'feature_impact_json' => ['past_overtime' => 0.6],
        'fallback_used' => 1,
        'created_at' => now(),
    ]);
    expect($prediction->predicted_value)->toBe('520.25')
        ->and($prediction->confidence_interval_lower)->toBe('490.10')
        ->and($prediction->confidence_interval_upper)->toBe('550.40')
        ->and($prediction->risk_score)->toBe('0.1234')
        ->and($prediction->fallback_used)->toBeTrue()
        ->and($prediction->feature_impact_json)->toBeArray();

    $anomaly = MlAnomalyLog::create([
        'overtime_item_id' => $item->id,
        'ml_model_id' => $mlModel->id,
        'anomaly_score' => 0.9543,
        'anomaly_reasons' => ['Extreme hours anomaly'],
        'is_dismissed' => false,
        'created_at' => now(),
    ]);
    expect($anomaly->anomaly_score)->toBe('0.9543')
        ->and($anomaly->anomaly_reasons)->toBeArray()
        ->and($anomaly->is_dismissed)->toBeFalse();
});

test('relationships navigate correctly across all models and users', function () {
    $user = User::factory()->create();

    $dept = Department::create([
        'code' => 'MAINT',
        'name' => 'Maintenance',
        'cost_center_code' => 'CC-MAINT-001',
        'default_hourly_rate' => 55000.00,
        'is_active' => true,
    ]);

    $sec = Section::create([
        'department_id' => $dept->id,
        'code' => 'MAINT_ELEC',
        'name' => 'Electrical Maintenance',
        'is_active' => true,
    ]);

    $emp = Employee::create([
        'npk' => 'EMP-3001',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'full_name' => 'Ahmad Dahlan',
        'job_position' => 'Electrician',
        'hourly_rate' => 58000.00,
        'is_active' => true,
    ]);

    $cal = OperationalCalendar::create([
        'calendar_date' => '2026-09-08',
        'day_type' => 'HKN',
        'is_holiday' => false,
        'created_at' => now(),
    ]);

    $threshold = PolicyThreshold::create([
        'department_id' => $dept->id,
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
        'spkl_grace_period_days' => 2,
        'burn_warning_pct' => 100.00,
        'burn_danger_pct' => 115.00,
    ]);

    $capex = CapexProject::create([
        'project_code' => 'CPX-2026-003',
        'name' => 'Substation Upgrade',
        'department_id' => $dept->id,
        'allocated_labor_hours' => 300.00,
        'allocated_labor_budget_idr' => 18000000.00,
        'physical_progress_pct' => 10.00,
        'status' => 'ACTIVE',
        'start_date' => '2026-03-01',
        'target_end_date' => '2026-09-30',
    ]);

    $budget = OvertimeBudget::create([
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_hours' => 150.00,
        'planned_cost_idr' => 8700000.00,
    ]);

    $sub = OvertimeSubmission::create([
        'submission_code' => 'OT-20260908-003',
        'submission_date' => '2026-09-08',
        'operational_date' => $cal->calendar_date,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'submitted_by_user_id' => $user->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 3.00,
    ]);

    $spkl = SpklDocument::create([
        'overtime_submission_id' => $sub->id,
        'spkl_number' => 'SPKL-MAINT-001',
        'file_path' => 'spkl/maint.pdf',
        'status' => 'ATTACHED',
        'due_date' => '2026-09-10',
        'attached_by_user_id' => $user->id,
    ]);

    $item = OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'capex_project_id' => $capex->id,
        'hours_production' => 0.00,
        'hours_tpm' => 1.00,
        'hours_project' => 2.00,
        'hours_others' => 0.00,
        'hourly_rate_snapshot' => 58000.00,
        'total_cost_snapshot' => 174000.00,
        'status' => 'PENDING',
        'reviewed_by_user_id' => $user->id,
        'lock_version' => 1,
    ]);

    $audit = OvertimeItemAudit::create([
        'overtime_item_id' => $item->id,
        'action' => 'SUBMITTED',
        'actor_user_id' => $user->id,
        'previous_state' => null,
        'new_state' => ['status' => 'PENDING'],
        'created_at' => now(),
    ]);

    $snapshot = MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_budget_hours' => 150.00,
        'cumulative_actual_hours' => 50.00,
        'burn_zone' => 'ZONE_1_EXCELLENT',
        'last_recalculated_at' => now(),
    ]);

    $mlModel = MlModel::create([
        'model_key' => 'MODEL_TEST_01',
        'model_type' => 'DEMAND_FORECAST',
        'version' => '1.0',
        'algorithm_name' => 'QuantileGBM',
        'hyperparameters' => [],
        'metrics' => [],
        'is_active' => true,
        'trained_at' => now(),
    ]);

    $pred = MlPrediction::create([
        'ml_model_id' => $mlModel->id,
        'target_type' => 'SECTION',
        'target_id' => $sec->id,
        'prediction_horizon' => 'WEEK_NEXT',
        'predicted_value' => 35.00,
        'created_at' => now(),
    ]);

    $anomaly = MlAnomalyLog::create([
        'overtime_item_id' => $item->id,
        'ml_model_id' => $mlModel->id,
        'anomaly_score' => 0.8900,
        'anomaly_reasons' => ['Unusual ratio'],
        'dismissed_by_user_id' => $user->id,
        'dismissed_at' => now(),
        'created_at' => now(),
    ]);

    // Test Department relationships
    expect($dept->sections)->toHaveCount(1)
        ->and($dept->employees)->toHaveCount(1)
        ->and($dept->policyThresholds)->toHaveCount(1)
        ->and($dept->capexProjects)->toHaveCount(1)
        ->and($dept->overtimeBudgets)->toHaveCount(1)
        ->and($dept->overtimeSubmissions)->toHaveCount(1)
        ->and($dept->monthlyBurnSnapshots)->toHaveCount(1);

    // Test Section relationships
    expect($sec->department->id)->toBe($dept->id)
        ->and($sec->employees)->toHaveCount(1)
        ->and($sec->overtimeBudgets)->toHaveCount(1)
        ->and($sec->overtimeSubmissions)->toHaveCount(1)
        ->and($sec->monthlyBurnSnapshots)->toHaveCount(1);

    // Test Employee relationships
    expect($emp->department->id)->toBe($dept->id)
        ->and($emp->section->id)->toBe($sec->id)
        ->and($emp->overtimeItems)->toHaveCount(1);

    // Test OperationalCalendar relationships
    expect($cal->overtimeSubmissions)->toHaveCount(1);

    // Test PolicyThreshold relationships
    expect($threshold->department->id)->toBe($dept->id);

    // Test CapexProject relationships
    expect($capex->department->id)->toBe($dept->id)
        ->and($capex->overtimeItems)->toHaveCount(1);

    // Test OvertimeBudget relationships
    expect($budget->department->id)->toBe($dept->id)
        ->and($budget->section->id)->toBe($sec->id);

    // Test OvertimeSubmission relationships
    expect($sub->operationalCalendar->calendar_date->format('Y-m-d'))->toBe('2026-09-08')
        ->and($sub->department->id)->toBe($dept->id)
        ->and($sub->section->id)->toBe($sec->id)
        ->and($sub->submittedBy->id)->toBe($user->id)
        ->and($sub->spklDocument->id)->toBe($spkl->id)
        ->and($sub->items)->toHaveCount(1);

    // Test SpklDocument relationships
    expect($spkl->overtimeSubmission->id)->toBe($sub->id)
        ->and($spkl->attachedBy->id)->toBe($user->id);

    // Test OvertimeItem relationships
    expect($item->overtimeSubmission->id)->toBe($sub->id)
        ->and($item->employee->id)->toBe($emp->id)
        ->and($item->capexProject->id)->toBe($capex->id)
        ->and($item->reviewedBy->id)->toBe($user->id)
        ->and($item->audits)->toHaveCount(1)
        ->and($item->anomalyLogs)->toHaveCount(1);

    // Test OvertimeItemAudit relationships
    expect($audit->overtimeItem->id)->toBe($item->id)
        ->and($audit->actor->id)->toBe($user->id);

    // Test MonthlyBurnSnapshot relationships
    expect($snapshot->department->id)->toBe($dept->id)
        ->and($snapshot->section->id)->toBe($sec->id);

    // Test MlModel relationships
    expect($mlModel->predictions)->toHaveCount(1)
        ->and($mlModel->anomalyLogs)->toHaveCount(1);

    // Test MlPrediction relationships
    expect($pred->mlModel->id)->toBe($mlModel->id);

    // Test MlAnomalyLog relationships
    expect($anomaly->overtimeItem->id)->toBe($item->id)
        ->and($anomaly->mlModel->id)->toBe($mlModel->id)
        ->and($anomaly->dismissedBy->id)->toBe($user->id);

    // Test User inverse relationships
    expect($user->overtimeSubmissions)->toHaveCount(1)
        ->and($user->reviewedOvertimeItems)->toHaveCount(1)
        ->and($user->attachedSpklDocuments)->toHaveCount(1)
        ->and($user->overtimeItemAudits)->toHaveCount(1)
        ->and($user->dismissedAnomalyLogs)->toHaveCount(1);
});

test('query scopes filter records correctly across all models', function () {
    $user = User::factory()->create();

    $deptActive = Department::create([
        'code' => 'DEPT_A',
        'name' => 'Active Dept',
        'cost_center_code' => 'CC-A',
        'is_active' => true,
    ]);

    $deptInactive = Department::create([
        'code' => 'DEPT_I',
        'name' => 'Inactive Dept',
        'cost_center_code' => 'CC-I',
        'is_active' => false,
    ]);

    expect(Department::active()->pluck('id'))->toContain($deptActive->id)
        ->and(Department::active()->pluck('id'))->not->toContain($deptInactive->id);

    $sec1 = Section::create([
        'department_id' => $deptActive->id,
        'code' => 'SEC_1',
        'name' => 'Section 1',
        'is_active' => true,
    ]);

    $sec2 = Section::create([
        'department_id' => $deptActive->id,
        'code' => 'SEC_2',
        'name' => 'Section 2',
        'is_active' => false,
    ]);

    expect(Section::active()->pluck('id'))->toContain($sec1->id)
        ->and(Section::active()->pluck('id'))->not->toContain($sec2->id)
        ->and(Section::forDepartment($deptActive->id)->count())->toBe(2);

    $emp1 = Employee::create([
        'npk' => 'EMP-S1',
        'department_id' => $deptActive->id,
        'section_id' => $sec1->id,
        'full_name' => 'Worker 1',
        'job_position' => 'Operator',
        'is_active' => true,
    ]);

    $emp2 = Employee::create([
        'npk' => 'EMP-S2',
        'department_id' => $deptActive->id,
        'section_id' => $sec1->id,
        'full_name' => 'Worker 2',
        'job_position' => 'Operator',
        'is_active' => false,
    ]);

    $emp3 = Employee::create([
        'npk' => 'EMP-S3',
        'department_id' => $deptActive->id,
        'section_id' => $sec2->id,
        'full_name' => 'Worker 3',
        'job_position' => 'Operator',
        'is_active' => true,
    ]);

    expect(Employee::active()->pluck('id'))->toContain($emp1->id, $emp3->id)
        ->and(Employee::forSection($sec1->id)->pluck('id'))->toContain($emp1->id, $emp2->id)
        ->and(Employee::forDepartment($deptActive->id)->count())->toBe(3)
        ->and(Employee::activeInSection($sec1->id)->pluck('id'))->toContain($emp1->id)
        ->and(Employee::activeInSection($sec1->id)->pluck('id'))->not->toContain($emp2->id);

    $workday = OperationalCalendar::create([
        'calendar_date' => '2026-09-09',
        'day_type' => 'HKN',
        'is_holiday' => false,
        'created_at' => now(),
    ]);

    $holiday = OperationalCalendar::create([
        'calendar_date' => '2026-09-10',
        'day_type' => 'HLR',
        'is_holiday' => true,
        'holiday_name' => 'Factory Holiday',
        'created_at' => now(),
    ]);

    expect(OperationalCalendar::workday()->count())->toBe(1)
        ->and(OperationalCalendar::holiday()->count())->toBe(1)
        ->and(OperationalCalendar::forDate('2026-09-09')->first()?->calendar_date->format('Y-m-d'))->toBe('2026-09-09');

    $plantThreshold = PolicyThreshold::create([
        'department_id' => null,
        'weekly_soft_limit_hours' => 20.0,
    ]);
    $deptThreshold = PolicyThreshold::create([
        'department_id' => $deptActive->id,
        'weekly_soft_limit_hours' => 24.0,
    ]);

    expect(PolicyThreshold::plantDefault()->pluck('id'))->toContain($plantThreshold->id)
        ->and(PolicyThreshold::plantDefault()->pluck('id'))->not->toContain($deptThreshold->id)
        ->and(PolicyThreshold::forDepartment($deptActive->id)->pluck('id'))->toContain($deptThreshold->id);

    $cpxActive = CapexProject::create([
        'project_code' => 'CPX-ACT',
        'name' => 'Active Project',
        'department_id' => $deptActive->id,
        'status' => 'ACTIVE',
        'start_date' => '2026-01-01',
        'target_end_date' => '2026-12-31',
    ]);
    $cpxClosed = CapexProject::create([
        'project_code' => 'CPX-CLS',
        'name' => 'Closed Project',
        'department_id' => $deptActive->id,
        'status' => 'CLOSED',
        'start_date' => '2026-01-01',
        'target_end_date' => '2026-06-30',
    ]);

    expect(CapexProject::active()->pluck('id'))->toContain($cpxActive->id)
        ->and(CapexProject::active()->pluck('id'))->not->toContain($cpxClosed->id)
        ->and(CapexProject::forDepartment($deptActive->id)->count())->toBe(2);

    $budgetSept = OvertimeBudget::create([
        'department_id' => $deptActive->id,
        'section_id' => $sec1->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_hours' => 100.00,
    ]);
    $budgetOct = OvertimeBudget::create([
        'department_id' => $deptActive->id,
        'section_id' => $sec1->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 10,
        'planned_hours' => 120.00,
    ]);

    expect(OvertimeBudget::forPeriod(2026, 9)->pluck('id'))->toContain($budgetSept->id)
        ->and(OvertimeBudget::forPeriod(2026, 9)->pluck('id'))->not->toContain($budgetOct->id)
        ->and(OvertimeBudget::forSection($sec1->id)->count())->toBe(2)
        ->and(OvertimeBudget::forDepartment($deptActive->id)->count())->toBe(2);

    $subSubmitted = OvertimeSubmission::create([
        'submission_code' => 'OT-SUB-01',
        'submission_date' => '2026-09-09',
        'operational_date' => '2026-09-09',
        'day_type' => 'HKN',
        'department_id' => $deptActive->id,
        'section_id' => $sec1->id,
        'submitted_by_user_id' => $user->id,
        'status' => 'SUBMITTED',
    ]);
    $subApproved = OvertimeSubmission::create([
        'submission_code' => 'OT-SUB-02',
        'submission_date' => '2026-09-09',
        'operational_date' => '2026-09-09',
        'day_type' => 'HKN',
        'department_id' => $deptActive->id,
        'section_id' => $sec1->id,
        'submitted_by_user_id' => $user->id,
        'status' => 'APPROVED',
    ]);

    expect(OvertimeSubmission::pending()->pluck('id'))->toContain($subSubmitted->id)
        ->and(OvertimeSubmission::pending()->pluck('id'))->not->toContain($subApproved->id)
        ->and(OvertimeSubmission::approved()->pluck('id'))->toContain($subApproved->id)
        ->and(OvertimeSubmission::forSection($sec1->id)->count())->toBe(2)
        ->and(OvertimeSubmission::forDepartment($deptActive->id)->count())->toBe(2)
        ->and(OvertimeSubmission::forDate('2026-09-09')->count())->toBe(2);

    $spklPending = SpklDocument::create([
        'overtime_submission_id' => $subSubmitted->id,
        'status' => 'PENDING',
        'due_date' => now()->subDay()->toDateString(),
    ]);
    $spklAttached = SpklDocument::create([
        'overtime_submission_id' => $subApproved->id,
        'status' => 'ATTACHED',
        'due_date' => now()->addDays(2)->toDateString(),
    ]);

    expect(SpklDocument::pending()->pluck('id'))->toContain($spklPending->id)
        ->and(SpklDocument::attached()->pluck('id'))->toContain($spklAttached->id)
        ->and(SpklDocument::overdue()->pluck('id'))->toContain($spklPending->id)
        ->and(SpklDocument::overdue()->pluck('id'))->not->toContain($spklAttached->id);

    $itemApproved = OvertimeItem::create([
        'overtime_submission_id' => $subApproved->id,
        'employee_id' => $emp1->id,
        'npk_snapshot' => $emp1->npk,
        'capex_project_id' => $cpxActive->id,
        'hours_production' => 1.00,
        'status' => 'APPROVED',
    ]);
    $itemPending = OvertimeItem::create([
        'overtime_submission_id' => $subSubmitted->id,
        'employee_id' => $emp1->id,
        'npk_snapshot' => $emp1->npk,
        'capex_project_id' => null,
        'hours_production' => 1.00,
        'status' => 'PENDING',
    ]);
    $itemRejected = OvertimeItem::create([
        'overtime_submission_id' => $subSubmitted->id,
        'employee_id' => $emp3->id,
        'npk_snapshot' => $emp3->npk,
        'capex_project_id' => null,
        'hours_production' => 1.00,
        'status' => 'REJECTED',
        'rejection_reason' => 'Invalid assignment',
    ]);

    expect(OvertimeItem::approved()->pluck('id'))->toContain($itemApproved->id)
        ->and(OvertimeItem::pending()->pluck('id'))->toContain($itemPending->id)
        ->and(OvertimeItem::rejected()->pluck('id'))->toContain($itemRejected->id)
        ->and(OvertimeItem::byEmployee($emp1->id)->pluck('id'))->toContain($itemApproved->id, $itemPending->id)
        ->and(OvertimeItem::capex()->pluck('id'))->toContain($itemApproved->id)
        ->and(OvertimeItem::capex()->pluck('id'))->not->toContain($itemPending->id);

    $auditSubmit = OvertimeItemAudit::create([
        'overtime_item_id' => $itemApproved->id,
        'action' => 'SUBMITTED',
        'actor_user_id' => $user->id,
        'previous_state' => null,
        'new_state' => ['status' => 'SUBMITTED'],
        'created_at' => now(),
    ]);

    expect(OvertimeItemAudit::forAction('SUBMITTED')->pluck('id'))->toContain($auditSubmit->id)
        ->and(OvertimeItemAudit::forActor($user->id)->pluck('id'))->toContain($auditSubmit->id);

    $snapWarning = MonthlyBurnSnapshot::create([
        'department_id' => $deptActive->id,
        'section_id' => $sec1->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'burn_zone' => 'ZONE_3_WARNING',
        'last_recalculated_at' => now(),
    ]);

    expect(MonthlyBurnSnapshot::warningOrDanger()->pluck('id'))->toContain($snapWarning->id)
        ->and(MonthlyBurnSnapshot::forPeriod(2026, 9)->pluck('id'))->toContain($snapWarning->id)
        ->and(MonthlyBurnSnapshot::forSection($sec1->id)->pluck('id'))->toContain($snapWarning->id)
        ->and(MonthlyBurnSnapshot::forDepartment($deptActive->id)->pluck('id'))->toContain($snapWarning->id);

    $modelForecast = MlModel::create([
        'model_key' => 'FCST_DEMAND_01',
        'model_type' => 'DEMAND_FORECAST',
        'version' => '1.0',
        'algorithm_name' => 'AutoARIMA',
        'hyperparameters' => [],
        'metrics' => [],
        'is_active' => true,
        'trained_at' => now(),
    ]);

    expect(MlModel::active()->pluck('id'))->toContain($modelForecast->id)
        ->and(MlModel::forType('DEMAND_FORECAST')->pluck('id'))->toContain($modelForecast->id);

    $predHigh = MlPrediction::create([
        'ml_model_id' => $modelForecast->id,
        'target_type' => 'SECTION',
        'target_id' => $sec1->id,
        'prediction_horizon' => 'MONTH_END',
        'predicted_value' => 120.00,
        'risk_level' => 'HIGH',
        'created_at' => now(),
    ]);

    expect(MlPrediction::forTarget('SECTION', $sec1->id)->pluck('id'))->toContain($predHigh->id)
        ->and(MlPrediction::highRisk()->pluck('id'))->toContain($predHigh->id);

    $anomalyPending = MlAnomalyLog::create([
        'overtime_item_id' => $itemPending->id,
        'ml_model_id' => $modelForecast->id,
        'anomaly_score' => 0.8500,
        'anomaly_reasons' => ['High spike'],
        'is_dismissed' => false,
        'created_at' => now(),
    ]);
    $anomalyDismissed = MlAnomalyLog::create([
        'overtime_item_id' => $itemApproved->id,
        'ml_model_id' => $modelForecast->id,
        'anomaly_score' => 0.6000,
        'anomaly_reasons' => ['Minor drift'],
        'is_dismissed' => true,
        'dismissed_by_user_id' => $user->id,
        'dismissed_at' => now(),
        'created_at' => now(),
    ]);

    expect(MlAnomalyLog::pending()->pluck('id'))->toContain($anomalyPending->id)
        ->and(MlAnomalyLog::dismissed()->pluck('id'))->toContain($anomalyDismissed->id)
        ->and(MlAnomalyLog::highAnomaly(0.8)->pluck('id'))->toContain($anomalyPending->id)
        ->and(MlAnomalyLog::highAnomaly(0.8)->pluck('id'))->not->toContain($anomalyDismissed->id);
});

test('nested eager loading across full overtime hierarchy executes cleanly', function () {
    $user = User::factory()->create();

    $dept = Department::create([
        'code' => 'BODY',
        'name' => 'Body Shop',
        'cost_center_code' => 'CC-BODY-001',
        'default_hourly_rate' => 51000.00,
    ]);

    $sec = Section::create([
        'department_id' => $dept->id,
        'code' => 'BODY_WELD',
        'name' => 'Welding Section',
    ]);

    $emp = Employee::create([
        'npk' => 'EMP-BODY-01',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'full_name' => 'Hendro Pratama',
        'job_position' => 'Welder',
        'hourly_rate' => 53000.00,
    ]);

    $cal = OperationalCalendar::create([
        'calendar_date' => '2026-09-11',
        'day_type' => 'HKN',
        'is_holiday' => false,
        'created_at' => now(),
    ]);

    $capex = CapexProject::create([
        'project_code' => 'CPX-BODY-01',
        'name' => 'Spot Welding Robot Upgrade',
        'department_id' => $dept->id,
        'allocated_labor_hours' => 200.00,
        'start_date' => '2026-01-01',
        'target_end_date' => '2026-12-31',
    ]);

    $sub = OvertimeSubmission::create([
        'submission_code' => 'OT-BODY-001',
        'submission_date' => '2026-09-11',
        'operational_date' => $cal->calendar_date,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'submitted_by_user_id' => $user->id,
        'status' => 'SUBMITTED',
    ]);

    $spkl = SpklDocument::create([
        'overtime_submission_id' => $sub->id,
        'spkl_number' => 'SPKL-BODY-001',
        'due_date' => '2026-09-13',
    ]);

    $item = OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'capex_project_id' => $capex->id,
        'hours_production' => 1.50,
        'hours_project' => 1.50,
        'status' => 'APPROVED',
        'reviewed_by_user_id' => $user->id,
        'lock_version' => 1,
    ]);

    $audit = OvertimeItemAudit::create([
        'overtime_item_id' => $item->id,
        'action' => 'APPROVED',
        'actor_user_id' => $user->id,
        'previous_state' => ['status' => 'PENDING'],
        'new_state' => ['status' => 'APPROVED'],
        'created_at' => now(),
    ]);

    $loadedSubmission = OvertimeSubmission::with([
        'department.sections',
        'section.employees',
        'operationalCalendar',
        'submittedBy',
        'spklDocument.attachedBy',
        'items.employee.department',
        'items.capexProject',
        'items.reviewedBy',
        'items.audits.actor',
    ])->find($sub->id);

    expect($loadedSubmission)->not->toBeNull()
        ->and($loadedSubmission->items)->toHaveCount(1)
        ->and($loadedSubmission->items->first()?->employee->full_name)->toBe('Hendro Pratama')
        ->and($loadedSubmission->items->first()?->capexProject?->project_code)->toBe('CPX-BODY-01')
        ->and($loadedSubmission->items->first()?->audits->first()?->action)->toBe('APPROVED')
        ->and($loadedSubmission->spklDocument?->spkl_number)->toBe('SPKL-BODY-001');
});

test('models without updated_at maintain immutable audit timestamps without errors', function () {
    $user = User::factory()->create();

    $dept = Department::create([
        'code' => 'PAINT',
        'name' => 'Paint Shop',
        'cost_center_code' => 'CC-PAINT-001',
    ]);

    $sec = Section::create([
        'department_id' => $dept->id,
        'code' => 'PAINT_BOOTH',
        'name' => 'Spray Booth',
    ]);

    $emp = Employee::create([
        'npk' => 'EMP-PAINT-01',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'full_name' => 'Bambang Supriyanto',
        'job_position' => 'Painter',
    ]);

    $cal = OperationalCalendar::create([
        'calendar_date' => '2026-09-12',
        'day_type' => 'HKN',
        'is_holiday' => false,
        'created_at' => now(),
    ]);

    $sub = OvertimeSubmission::create([
        'submission_code' => 'OT-PAINT-001',
        'submission_date' => '2026-09-12',
        'operational_date' => $cal->calendar_date,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'submitted_by_user_id' => $user->id,
    ]);

    $item = OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 2.00,
        'lock_version' => 1,
    ]);

    $audit = OvertimeItemAudit::create([
        'overtime_item_id' => $item->id,
        'action' => 'SUBMITTED',
        'actor_user_id' => $user->id,
        'previous_state' => null,
        'new_state' => ['status' => 'PENDING'],
        'created_at' => now(),
    ]);

    expect($audit->created_at)->toBeInstanceOf(CarbonInterface::class);

    // Verify optimistic locking lock_version increment on OvertimeItem
    $item->increment('lock_version');
    $item->refresh();
    expect($item->lock_version)->toBe(2);

    // Verify non-timestamped monthly burn snapshot save
    $snapshot = MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_budget_hours' => 100.00,
        'last_recalculated_at' => now(),
    ]);
    expect($snapshot->last_recalculated_at)->toBeInstanceOf(CarbonInterface::class);
});
