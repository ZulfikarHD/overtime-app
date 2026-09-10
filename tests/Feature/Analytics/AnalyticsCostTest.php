<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $now = Carbon::now('Asia/Jakarta');
    OperationalCalendar::firstOrCreate(
        ['calendar_date' => $now->toDateString()],
        ['day_type' => 'HKN', 'is_holiday' => false]
    );
});

test('guest is redirected to login when accessing cost analysis endpoint', function () {
    $this->get(route('analytics.cost'))->assertRedirect(route('login'));
});

test('operator and team leader are forbidden from accessing cost analysis endpoint', function () {
    $operator = User::factory()->user()->create();
    $teamLeader = User::factory()->teamLeader()->create();

    $this->actingAs($operator)
        ->get(route('analytics.cost'))
        ->assertForbidden();

    $this->actingAs($teamLeader)
        ->get(route('analytics.cost'))
        ->assertForbidden();
});

test('admin can access cost analysis endpoint and receives complete json structure', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create(['name' => 'Assembly Department', 'code' => 'ASY-01', 'is_active' => true]);
    $sec = Section::factory()->create(['department_id' => $dept->id, 'code' => 'ASY-SEC-01', 'name' => 'Trim Line', 'is_active' => true]);
    $emp = Employee::factory()->create(['department_id' => $dept->id, 'section_id' => $sec->id, 'hourly_rate' => 50000]);

    $now = Carbon::now('Asia/Jakarta');
    $fiscalYear = (int) $now->year;
    $fiscalMonth = (int) $now->month;

    OvertimeBudget::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'fiscal_year' => $fiscalYear,
        'fiscal_month' => $fiscalMonth,
        'planned_cost_idr' => 10000000.00,
        'planned_hours' => 200.0,
    ]);

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-TEST-COST-001',
        'submission_date' => $now->toDateString(),
        'operational_date' => $now->toDateString(),
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'submitted_by_user_id' => $admin->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 10.0,
        'hours_tpm' => 0.0,
        'hours_project' => 5.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.00,
        'total_cost_snapshot' => 750000.00,
        'status' => 'APPROVED',
        'lock_version' => 1,
    ]);

    $response = $this->actingAs($admin)->get(route('analytics.cost'));

    $response->assertOk();
    $response->assertJsonStructure([
        'kpi' => [
            'total_cost',
            'formatted_total_cost',
            'planned_budget',
            'formatted_planned_budget',
            'remaining_budget',
            'formatted_remaining_budget',
            'budget_consumption_pct',
            'has_budget',
            'avg_cost_per_employee',
            'formatted_avg_cost_per_employee',
            'active_employee_count',
            'capex_cost',
            'formatted_capex_cost',
            'opex_cost',
            'formatted_opex_cost',
            'capex_ratio_pct',
        ],
        'department_costs' => [
            '*' => [
                'department_id',
                'department_code',
                'department_name',
                'total_hours',
                'total_cost',
                'formatted_total_cost',
                'planned_cost',
                'formatted_planned_cost',
                'budget_consumption_pct',
                'is_over_budget',
                'burn_zone',
                'avg_rate_per_hour',
                'formatted_avg_rate',
                'capex_cost',
                'opex_cost',
                'trend',
                'trend_variance_pct',
            ],
        ],
        'monthly_trend_6m' => [
            'labels',
            'opex_series',
            'capex_series',
            'total_series',
        ],
        'budget_vs_actual' => [
            'labels',
            'planned_series',
            'actual_series',
            'variance_series',
            'is_section_breakdown',
        ],
        'scope' => [
            'department_id',
            'department_name',
            'start_date',
            'end_date',
            'fiscal_year',
            'fiscal_month',
        ],
    ]);

    $data = $response->json();
    expect($data['kpi']['total_cost'])->toEqual(750000);
    expect($data['kpi']['active_employee_count'])->toBe(1);
    expect($data['kpi']['capex_cost'])->toEqual(250000); // 5h * 50000
    expect($data['kpi']['opex_cost'])->toEqual(500000); // 10h * 50000
    expect($data['kpi']['capex_ratio_pct'])->toEqual(33.3); // 250000 / 750000 * 100%
});

test('manager is strictly scoped to assigned department in cost analysis', function () {
    $deptA = Department::factory()->create(['name' => 'Dept Alpha', 'code' => 'D-A', 'is_active' => true]);
    $deptB = Department::factory()->create(['name' => 'Dept Beta', 'code' => 'D-B', 'is_active' => true]);

    $managerA = User::factory()->manager($deptA->id)->create();

    $secA = Section::factory()->create(['department_id' => $deptA->id, 'is_active' => true]);
    $secB = Section::factory()->create(['department_id' => $deptB->id, 'is_active' => true]);

    $empA = Employee::factory()->create(['department_id' => $deptA->id, 'section_id' => $secA->id]);
    $empB = Employee::factory()->create(['department_id' => $deptB->id, 'section_id' => $secB->id]);

    $now = Carbon::now('Asia/Jakarta');

    // Submission for Dept A
    $subA = OvertimeSubmission::create([
        'submission_code' => 'OT-MGR-A-01',
        'submission_date' => $now->toDateString(),
        'operational_date' => $now->toDateString(),
        'day_type' => 'HKN',
        'department_id' => $deptA->id,
        'section_id' => $secA->id,
        'submitted_by_user_id' => $managerA->id,
        'status' => 'APPROVED',
    ]);
    OvertimeItem::create([
        'overtime_submission_id' => $subA->id,
        'employee_id' => $empA->id,
        'npk_snapshot' => $empA->npk,
        'hours_production' => 8.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 40000.00,
        'total_cost_snapshot' => 320000.00,
        'status' => 'APPROVED',
    ]);

    // Submission for Dept B
    $subB = OvertimeSubmission::create([
        'submission_code' => 'OT-MGR-B-01',
        'submission_date' => $now->toDateString(),
        'operational_date' => $now->toDateString(),
        'day_type' => 'HKN',
        'department_id' => $deptB->id,
        'section_id' => $secB->id,
        'submitted_by_user_id' => $managerA->id,
        'status' => 'APPROVED',
    ]);
    OvertimeItem::create([
        'overtime_submission_id' => $subB->id,
        'employee_id' => $empB->id,
        'npk_snapshot' => $empB->npk,
        'hours_production' => 12.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.00,
        'total_cost_snapshot' => 600000.00,
        'status' => 'APPROVED',
    ]);

    // Manager A attempts to request Dept B's data
    $response = $this->actingAs($managerA)->get(route('analytics.cost', ['department_id' => $deptB->id]));

    $response->assertOk();
    $data = $response->json();

    // Forced scoping to Dept A
    expect($data['scope']['department_id'])->toBe($deptA->id);
    expect($data['kpi']['total_cost'])->toEqual(320000);
    expect(count($data['department_costs']))->toBe(1);
    expect($data['department_costs'][0]['department_id'])->toBe($deptA->id);
});

test('analytics index supplies costData prop when tab is cost', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('analytics.index', ['tab' => 'cost']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Analytics/Index')
        ->where('currentTab', 'cost')
        ->has('costData')
        ->where('costData.kpi.total_cost', fn ($val) => is_numeric($val))
    );
});

test('export endpoint generates csv with cost data when tab is cost', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create(['name' => 'Painting Department', 'code' => 'PNT-01', 'is_active' => true]);
    $sec = Section::factory()->create(['department_id' => $dept->id, 'is_active' => true]);
    $emp = Employee::factory()->create(['department_id' => $dept->id, 'section_id' => $sec->id]);

    $now = Carbon::now('Asia/Jakarta');
    $sub = OvertimeSubmission::create([
        'submission_code' => 'OT-EXP-COST-01',
        'submission_date' => $now->toDateString(),
        'operational_date' => $now->toDateString(),
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'submitted_by_user_id' => $admin->id,
        'status' => 'APPROVED',
    ]);
    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 6.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 45000.00,
        'total_cost_snapshot' => 270000.00,
        'status' => 'APPROVED',
    ]);

    $response = $this->actingAs($admin)->get(route('analytics.export', [
        'tab' => 'cost',
        'format' => 'csv',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});

test('export endpoint generates pdf with cost data when tab is cost', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create(['name' => 'Quality Department', 'code' => 'QC-01', 'is_active' => true]);

    $response = $this->actingAs($admin)->get(route('analytics.export', [
        'tab' => 'cost',
        'format' => 'pdf',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});
