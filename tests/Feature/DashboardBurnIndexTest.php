<?php

use App\Jobs\RecalculateMonthlyBurnSnapshotJob;
use App\Models\Department;
use App\Models\Employee;
use App\Models\MlModel;
use App\Models\MlPrediction;
use App\Models\MonthlyBurnSnapshot;
use App\Models\OperationalCalendar;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function ensureCalendarForDate(string $date = '2026-09-08'): void
{
    if (! OperationalCalendar::whereDate('calendar_date', $date)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $date,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }
}

test('guest is redirected to login when accessing burn index routes', function () {
    $this->get(route('dashboard.burn-index'))->assertRedirect(route('login'));
    $this->post(route('dashboard.burn-index.recalculate'))->assertRedirect(route('login'));
});

test('operator is forbidden from accessing burn index dashboard', function () {
    $operator = User::factory()->user()->create();

    $this->actingAs($operator)
        ->get(route('dashboard.burn-index'))
        ->assertForbidden();

    $this->actingAs($operator)
        ->post(route('dashboard.burn-index.recalculate'))
        ->assertForbidden();
});

test('manager can access burn index and sees only sections in their department', function () {
    $deptA = Department::factory()->create(['name' => 'Assembly Dept', 'is_active' => true]);
    $deptB = Department::factory()->create(['name' => 'Machining Dept', 'is_active' => true]);

    $secA1 = Section::factory()->create(['department_id' => $deptA->id, 'name' => 'Trim Line', 'is_active' => true]);
    $secA2 = Section::factory()->create(['department_id' => $deptA->id, 'name' => 'Chassis Line', 'is_active' => true]);
    $secB = Section::factory()->create(['department_id' => $deptB->id, 'name' => 'CNC Milling', 'is_active' => true]);

    $manager = User::factory()->manager($deptA->id)->create();

    $response = $this->actingAs($manager)->get(route('dashboard.burn-index', [
        'year' => 2026,
        'month' => 9,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard/BurnIndex')
        ->where('fiscal_year', 2026)
        ->where('fiscal_month', 9)
        ->where('selected_department.id', $deptA->id)
        ->has('snapshots', 2)
        ->where('snapshots.0.section_id', fn ($id) => in_array($id, [$secA1->id, $secA2->id]))
        ->where('snapshots.1.section_id', fn ($id) => in_array($id, [$secA1->id, $secA2->id]))
    );
});

test('team leader can access burn index and sees only their assigned section', function () {
    $dept = Department::factory()->create(['is_active' => true]);
    $sec1 = Section::factory()->create(['department_id' => $dept->id, 'name' => 'Line 1', 'is_active' => true]);
    $sec2 = Section::factory()->create(['department_id' => $dept->id, 'name' => 'Line 2', 'is_active' => true]);

    $teamLeader = User::factory()->teamLeader($sec1->id, $dept->id)->create();

    $response = $this->actingAs($teamLeader)->get(route('dashboard.burn-index', [
        'year' => 2026,
        'month' => 9,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard/BurnIndex')
        ->has('snapshots', 1)
        ->where('snapshots.0.section_id', $sec1->id)
    );
});

test('admin can switch departments and see all active departments', function () {
    $deptA = Department::factory()->create(['code' => 'DEPT_A', 'is_active' => true]);
    $deptB = Department::factory()->create(['code' => 'DEPT_B', 'is_active' => true]);

    $secA = Section::factory()->create(['department_id' => $deptA->id, 'is_active' => true]);
    $secB = Section::factory()->create(['department_id' => $deptB->id, 'is_active' => true]);

    $admin = User::factory()->admin()->create();

    // Query Dept B explicitly
    $response = $this->actingAs($admin)->get(route('dashboard.burn-index', [
        'year' => 2026,
        'month' => 9,
        'department_id' => $deptB->id,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard/BurnIndex')
        ->where('selected_department.id', $deptB->id)
        ->has('snapshots', 1)
        ->where('snapshots.0.section_id', $secB->id)
    );
});

test('snapshots are read from monthly_burn_snapshots table and reflect stored data', function () {
    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create(['department_id' => $dept->id, 'is_active' => true]);

    MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_budget_hours' => 250.0,
        'cumulative_actual_hours' => 200.0,
        'cumulative_opex_hours' => 150.0,
        'cumulative_capex_hours' => 50.0,
        'burn_index_pct' => 80.0,
        'burn_velocity' => 46.5,
        'burn_zone' => 'ZONE_2_GOOD',
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $manager = User::factory()->manager($dept->id)->create();

    $response = $this->actingAs($manager)->get(route('dashboard.burn-index', [
        'year' => 2026,
        'month' => 9,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard/BurnIndex')
        ->where('snapshots.0.burn_index_pct', 80)
        ->where('snapshots.0.planned_budget_hours', 250)
        ->where('snapshots.0.cumulative_actual_hours', 200)
        ->where('snapshots.0.burn_zone', 'ZONE_2_GOOD')
        ->where('snapshots.0.is_budget_configured', true)
        ->where('summary.total_planned_hours', 250)
        ->where('summary.total_actual_hours', 200)
        ->where('summary.department_burn_index_pct', 80)
    );
});

test('unconfigured section budget displays safe empty state info without crashing', function () {
    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create(['department_id' => $dept->id, 'is_active' => true]);

    // No OvertimeBudget and no pre-existing snapshot
    $manager = User::factory()->manager($dept->id)->create();

    $response = $this->actingAs($manager)->get(route('dashboard.burn-index', [
        'year' => 2026,
        'month' => 9,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard/BurnIndex')
        ->where('snapshots.0.is_budget_configured', false)
        ->where('snapshots.0.burn_index_pct', 0)
        ->where('snapshots.0.planned_budget_hours', 0)
    );
});

test('admin can trigger recalculate endpoint to refresh department snapshots', function () {
    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create(['department_id' => $dept->id, 'is_active' => true]);

    OvertimeBudget::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_hours' => 150.0,
    ]);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('dashboard.burn-index.recalculate', [
        'department_id' => $dept->id,
        'year' => 2026,
        'month' => 9,
    ]));

    $response->assertRedirect();
    $this->assertDatabaseHas('monthly_burn_snapshots', [
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_budget_hours' => 150.0,
    ]);
});

test('recalculate job asynchronously updates section snapshot in database', function () {
    ensureCalendarForDate('2026-09-08');

    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create(['department_id' => $dept->id, 'is_active' => true]);

    OvertimeBudget::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_hours' => 100.0,
    ]);

    $user = User::factory()->create(['department_id' => $dept->id, 'section_id' => $section->id]);
    $emp = Employee::factory()->create(['department_id' => $dept->id, 'section_id' => $section->id, 'hourly_rate' => 30000]);

    $sub = OvertimeSubmission::create([
        'submission_code' => 'OT-JOB-TEST-'.uniqid(),
        'submission_date' => '2026-09-08',
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $user->id,
        'status' => 'APPROVED',
        'total_hours_cached' => 75.0,
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 75.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 30000,
        'total_cost_snapshot' => 75.0 * 30000,
        'status' => 'APPROVED',
    ]);

    $job = new RecalculateMonthlyBurnSnapshotJob($section->id, 2026, 9);
    app()->call([$job, 'handle']);

    $this->assertDatabaseHas('monthly_burn_snapshots', [
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_budget_hours' => 100.0,
        'cumulative_actual_hours' => 75.0,
        'burn_index_pct' => 75.0,
    ]);
});

test('dashboard burn index props include velocity projected total trajectory and ml forecast', function () {
    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create(['department_id' => $dept->id, 'is_active' => true]);

    MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_budget_hours' => 200.0,
        'cumulative_actual_hours' => 150.0,
        'cumulative_opex_hours' => 100.0,
        'cumulative_capex_hours' => 50.0,
        'burn_index_pct' => 75.0,
        'burn_velocity' => 37.5,
        'burn_zone' => 'ZONE_1_EXCELLENT',
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $mlModel = MlModel::create([
        'model_key' => 'demand-ridge-v1',
        'model_type' => 'DEMAND_FORECAST',
        'version' => '1.0.0',
        'algorithm_name' => 'RidgeRegression',
        'is_active' => true,
        'trained_at' => now(),
    ]);

    MlPrediction::create([
        'ml_model_id' => $mlModel->id,
        'target_type' => 'SECTION',
        'target_id' => $section->id,
        'prediction_horizon' => 'MONTH_END',
        'predicted_value' => 175.00,
        'confidence_interval_lower' => 163.00,
        'confidence_interval_upper' => 187.00,
        'risk_score' => 0.1500,
        'risk_level' => 'LOW',
        'fallback_used' => false,
        'created_at' => now(),
    ]);

    $manager = User::factory()->manager($dept->id)->create();

    $response = $this->actingAs($manager)->get(route('dashboard.burn-index', [
        'year' => 2026,
        'month' => 9,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard/BurnIndex')
        ->where('snapshots.0.burn_velocity', 37.5)
        ->where('snapshots.0.projected_total_hours', 161.3)
        ->where('snapshots.0.trajectory', 'on_pace')
        ->where('snapshots.0.ml_forecast.predicted_value', 175)
        ->where('snapshots.0.ml_forecast.confidence_delta', 12)
        ->where('snapshots.0.ml_forecast.risk_level', 'LOW')
    );
});

test('dashboard burn index props have null ml forecast when no prediction exists', function () {
    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create(['department_id' => $dept->id, 'is_active' => true]);

    MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_budget_hours' => 100.0,
        'cumulative_actual_hours' => 60.0,
        'cumulative_opex_hours' => 40.0,
        'cumulative_capex_hours' => 20.0,
        'burn_index_pct' => 60.0,
        'burn_velocity' => 25.0,
        'burn_zone' => 'ZONE_1_EXCELLENT',
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $manager = User::factory()->manager($dept->id)->create();

    $response = $this->actingAs($manager)->get(route('dashboard.burn-index', [
        'year' => 2026,
        'month' => 9,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard/BurnIndex')
        ->where('snapshots.0.burn_velocity', 25)
        ->where('snapshots.0.projected_total_hours', 107.5)
        ->where('snapshots.0.trajectory', 'trending_over')
        ->where('snapshots.0.ml_forecast', null)
    );
});
