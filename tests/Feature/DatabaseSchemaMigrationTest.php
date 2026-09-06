<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('all 15 epic-01 migration tables exist in the database schema', function () {
    $expectedTables = [
        'departments',
        'sections',
        'employees',
        'operational_calendars',
        'policy_thresholds',
        'capex_projects',
        'overtime_budgets',
        'overtime_submissions',
        'spkl_documents',
        'overtime_items',
        'overtime_item_audits',
        'monthly_burn_snapshots',
        'ml_models',
        'ml_predictions',
        'ml_anomaly_logs',
    ];

    foreach ($expectedTables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Table {$table} should exist in schema");
    }
});

test('overtime_items table computes and stores total_hours via stored generated column', function () {
    $user = User::factory()->create();

    $deptId = DB::table('departments')->insertGetId([
        'code' => 'PROD',
        'name' => 'Production Department',
        'cost_center_code' => 'CC-PROD-001',
        'default_hourly_rate' => 45000.00,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $sectionId = DB::table('sections')->insertGetId([
        'department_id' => $deptId,
        'code' => 'ASSY_LINE_1',
        'name' => 'Assembly Line 1',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $employeeId = DB::table('employees')->insertGetId([
        'npk' => 'EMP-1001',
        'department_id' => $deptId,
        'section_id' => $sectionId,
        'full_name' => 'Budi Santoso',
        'job_position' => 'Line Operator',
        'hourly_rate' => 48000.00,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $calendarDate = '2026-09-06';
    DB::table('operational_calendars')->insert([
        'calendar_date' => $calendarDate,
        'day_type' => 'HKN',
        'is_holiday' => false,
        'created_at' => now(),
    ]);

    $capexId = DB::table('capex_projects')->insertGetId([
        'project_code' => 'CPX-2026-001',
        'name' => 'Assembly Conveyor Upgrade',
        'department_id' => $deptId,
        'start_date' => '2026-01-01',
        'target_end_date' => '2026-12-31',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $submissionId = DB::table('overtime_submissions')->insertGetId([
        'submission_code' => 'OT-20260906-ASSY1-001',
        'submission_date' => '2026-09-06',
        'operational_date' => $calendarDate,
        'day_type' => 'HKN',
        'department_id' => $deptId,
        'section_id' => $sectionId,
        'submitted_by_user_id' => $user->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 0.00,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $itemId = DB::table('overtime_items')->insertGetId([
        'overtime_submission_id' => $submissionId,
        'employee_id' => $employeeId,
        'npk_snapshot' => 'EMP-1001',
        'capex_project_id' => $capexId,
        'hours_production' => 2.50,
        'hours_tpm' => 1.00,
        'hours_project' => 0.50,
        'hours_others' => 0.50,
        'hourly_rate_snapshot' => 48000.00,
        'total_cost_snapshot' => 216000.00,
        'status' => 'PENDING',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $item = DB::table('overtime_items')->where('id', $itemId)->first();

    expect((float) $item->total_hours)->toEqual(4.50);
});

test('foreign key on delete restrict protects parent records from deletion', function () {
    $deptId = DB::table('departments')->insertGetId([
        'code' => 'ENG',
        'name' => 'Engineering Department',
        'cost_center_code' => 'CC-ENG-001',
        'default_hourly_rate' => 55000.00,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('sections')->insert([
        'department_id' => $deptId,
        'code' => 'ENG_TOOLING',
        'name' => 'Tooling Section',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(function () use ($deptId) {
        DB::table('departments')->where('id', $deptId)->delete();
    })->toThrow(QueryException::class);
});

test('foreign key on delete cascade cleans up child records for submissions and items', function () {
    $user = User::factory()->create();

    $deptId = DB::table('departments')->insertGetId([
        'code' => 'MAINT',
        'name' => 'Maintenance Department',
        'cost_center_code' => 'CC-MAINT-001',
        'default_hourly_rate' => 50000.00,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $sectionId = DB::table('sections')->insertGetId([
        'department_id' => $deptId,
        'code' => 'MAINT_ELEC',
        'name' => 'Electrical Maintenance',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $employeeId = DB::table('employees')->insertGetId([
        'npk' => 'EMP-2001',
        'department_id' => $deptId,
        'section_id' => $sectionId,
        'full_name' => 'Ahmad Dahlan',
        'job_position' => 'Technician',
        'hourly_rate' => 52000.00,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $calendarDate = '2026-09-07';
    DB::table('operational_calendars')->insert([
        'calendar_date' => $calendarDate,
        'day_type' => 'HKN',
        'is_holiday' => false,
        'created_at' => now(),
    ]);

    $submissionId = DB::table('overtime_submissions')->insertGetId([
        'submission_code' => 'OT-20260907-MAINT-001',
        'submission_date' => '2026-09-07',
        'operational_date' => $calendarDate,
        'day_type' => 'HKN',
        'department_id' => $deptId,
        'section_id' => $sectionId,
        'submitted_by_user_id' => $user->id,
        'status' => 'SUBMITTED',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('spkl_documents')->insert([
        'overtime_submission_id' => $submissionId,
        'due_date' => '2026-09-09',
        'status' => 'PENDING',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $itemId = DB::table('overtime_items')->insertGetId([
        'overtime_submission_id' => $submissionId,
        'employee_id' => $employeeId,
        'npk_snapshot' => 'EMP-2001',
        'hours_production' => 3.00,
        'status' => 'PENDING',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('overtime_item_audits')->insert([
        'overtime_item_id' => $itemId,
        'action' => 'SUBMITTED',
        'actor_user_id' => $user->id,
        'previous_state' => null,
        'new_state' => json_encode(['status' => 'PENDING']),
        'created_at' => now(),
    ]);

    // Deleting the submission should cascade to spkl_documents, overtime_items, and overtime_item_audits
    DB::table('overtime_submissions')->where('id', $submissionId)->delete();

    expect(DB::table('spkl_documents')->where('overtime_submission_id', $submissionId)->count())->toBe(0);
    expect(DB::table('overtime_items')->where('id', $itemId)->count())->toBe(0);
    expect(DB::table('overtime_item_audits')->where('overtime_item_id', $itemId)->count())->toBe(0);
});

test('composite unique constraint on overtime_budgets prevents duplicate period entries', function () {
    $deptId = DB::table('departments')->insertGetId([
        'code' => 'LOG',
        'name' => 'Logistics Department',
        'cost_center_code' => 'CC-LOG-001',
        'default_hourly_rate' => 42000.00,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $sectionId = DB::table('sections')->insertGetId([
        'department_id' => $deptId,
        'code' => 'LOG_WH',
        'name' => 'Warehouse Section',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('overtime_budgets')->insert([
        'department_id' => $deptId,
        'section_id' => $sectionId,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_hours' => 200.00,
        'planned_cost_idr' => 8400000.00,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(function () use ($deptId, $sectionId) {
        DB::table('overtime_budgets')->insert([
            'department_id' => $deptId,
            'section_id' => $sectionId,
            'fiscal_year' => 2026,
            'fiscal_month' => 9,
            'planned_hours' => 250.00,
            'planned_cost_idr' => 10500000.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    })->toThrow(QueryException::class);
});

test('ml_models and ml_predictions persist json payloads accurately', function () {
    $modelId = DB::table('ml_models')->insertGetId([
        'model_key' => 'XGBOOST_BURN_V1',
        'model_type' => 'BURN_TRAJECTORY',
        'version' => '1.0.0',
        'algorithm_name' => 'Quantile Gradient Boosting',
        'hyperparameters' => json_encode(['n_estimators' => 100, 'max_depth' => 5]),
        'metrics' => json_encode(['mape' => 8.5, 'rmse' => 12.3]),
        'is_active' => true,
        'trained_at' => now(),
    ]);

    $predId = DB::table('ml_predictions')->insertGetId([
        'ml_model_id' => $modelId,
        'target_type' => 'SECTION',
        'target_id' => 1,
        'prediction_horizon' => 'MONTH_END',
        'predicted_value' => 450.75,
        'confidence_interval_lower' => 420.00,
        'confidence_interval_upper' => 485.50,
        'risk_score' => 0.8250,
        'risk_level' => 'HIGH',
        'feature_impact_json' => json_encode(['past_burn' => 0.65, 'spkl_backlog' => 0.25]),
        'fallback_used' => false,
        'created_at' => now(),
    ]);

    $model = DB::table('ml_models')->where('id', $modelId)->first();
    $prediction = DB::table('ml_predictions')->where('id', $predId)->first();

    $hyper = json_decode($model->hyperparameters, true);
    expect($hyper['max_depth'])->toBe(5);

    $impact = json_decode($prediction->feature_impact_json, true);
    expect($impact['past_burn'])->toBe(0.65);
});
