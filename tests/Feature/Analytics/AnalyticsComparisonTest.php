<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
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

test('guest is redirected to login when accessing comparison endpoint', function () {
    $this->get(route('analytics.comparison'))->assertRedirect(route('login'));
});

test('operator and team leader are forbidden from accessing comparison endpoint', function () {
    $operator = User::factory()->user()->create();
    $teamLeader = User::factory()->teamLeader()->create();

    $this->actingAs($operator)
        ->get(route('analytics.comparison'))
        ->assertForbidden();

    $this->actingAs($teamLeader)
        ->get(route('analytics.comparison'))
        ->assertForbidden();
});

test('admin can access comparison endpoint and receives complete json structure', function () {
    $admin = User::factory()->admin()->create();

    $deptA = Department::factory()->create(['name' => 'Assembly Department', 'code' => 'ASY-01', 'is_active' => true]);
    $secA = Section::factory()->create(['department_id' => $deptA->id, 'code' => 'ASY-SEC-01', 'name' => 'Trim Line', 'is_active' => true]);
    $empA = Employee::factory()->create(['department_id' => $deptA->id, 'section_id' => $secA->id, 'hourly_rate' => 50000]);

    $now = Carbon::now('Asia/Jakarta');

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-TEST-COMP-001',
        'submission_date' => $now->toDateString(),
        'operational_date' => $now->toDateString(),
        'day_type' => 'HKN',
        'department_id' => $deptA->id,
        'section_id' => $secA->id,
        'submitted_by_user_id' => $admin->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $empA->id,
        'npk_snapshot' => $empA->npk,
        'hours_production' => 10.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'total_hours' => 10.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 500000.0,
        'status' => 'APPROVED',
    ]);

    $response = $this->actingAs($admin)->get(route('analytics.comparison'));

    $response->assertOk();
    $response->assertJsonStructure([
        'comparison_type',
        'base_period',
        'compare_period',
        'base_period_label',
        'compare_period_label',
        'kpi' => [
            'total_hours' => [
                'base_value',
                'compare_value',
                'diff_value',
                'pct_change',
                'direction',
                'formatted_diff',
                'is_zero_baseline',
            ],
            'total_cost' => [
                'base_value',
                'compare_value',
                'diff_value',
                'pct_change',
                'direction',
                'formatted_diff',
                'formatted_base',
                'formatted_compare',
                'is_zero_baseline',
            ],
            'efficiency' => [
                'base_hours_per_unit',
                'compare_hours_per_unit',
                'diff_efficiency',
                'pct_change',
                'direction',
                'formatted_diff',
                'base_units_per_hour',
                'compare_units_per_hour',
                'erp_connected',
            ],
            'headcount' => [
                'base_value',
                'compare_value',
                'diff_value',
                'pct_change',
                'direction',
                'formatted_diff',
            ],
        ],
        'period_comparison_chart' => [
            'title',
            'labels',
            'base_series',
            'compare_series',
            'variance_series',
            'base_label',
            'compare_label',
        ],
        'department_benchmarks',
        'performers' => [
            'best',
            'average',
            'worst',
        ],
        'best_practice_cards',
        'scope' => [
            'department_id',
            'department_name',
            'is_manager_scoped',
        ],
    ]);
});

test('comparison correctly calculates metrics and handles zero compare period without error', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create(['name' => 'Welding Plant', 'code' => 'WLD-01', 'is_active' => true]);
    $sec = Section::factory()->create(['department_id' => $dept->id, 'code' => 'WLD-SEC-01', 'name' => 'Spot Welding', 'is_active' => true]);
    $emp = Employee::factory()->create(['department_id' => $dept->id, 'section_id' => $sec->id, 'hourly_rate' => 60000]);

    $baseDate = '2026-09-15';
    $compareDate = '2025-09-15';

    OperationalCalendar::firstOrCreate(
        ['calendar_date' => $baseDate],
        ['day_type' => 'HKN', 'is_holiday' => false]
    );
    OperationalCalendar::firstOrCreate(
        ['calendar_date' => $compareDate],
        ['day_type' => 'HKN', 'is_holiday' => false]
    );

    // Base period has submission
    $subBase = OvertimeSubmission::create([
        'submission_code' => 'OT-BASE-001',
        'submission_date' => $baseDate,
        'operational_date' => $baseDate,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'submitted_by_user_id' => $admin->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $subBase->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 20.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'total_hours' => 20.0,
        'hourly_rate_snapshot' => 60000.0,
        'total_cost_snapshot' => 1200000.0,
        'status' => 'APPROVED',
    ]);

    // Zero compare period (2025-09 has 0 hours)
    $response = $this->actingAs($admin)->get(route('analytics.comparison', [
        'base_period' => '2026-09',
        'compare_period' => '2025-09',
        'type' => 'yoy',
        'department_id' => $dept->id,
    ]));

    $response->assertOk();
    $data = $response->json();

    expect((float) $data['kpi']['total_hours']['base_value'])->toEqual(20.0);
    expect((float) $data['kpi']['total_hours']['compare_value'])->toEqual(0.0);
    expect($data['kpi']['total_hours']['is_zero_baseline'])->toBeTrue();
    expect((float) $data['kpi']['total_hours']['pct_change'])->toEqual(100.0);
    expect((float) $data['kpi']['total_cost']['base_value'])->toEqual(1200000.0);
    expect((float) $data['kpi']['total_cost']['compare_value'])->toEqual(0.0);
});

test('manager is strictly scoped to own department and benchmarks sections within that department', function () {
    $deptA = Department::factory()->create(['name' => 'Painting Dept', 'code' => 'PNT-01', 'is_active' => true]);
    $secA1 = Section::factory()->create(['department_id' => $deptA->id, 'code' => 'PNT-S1', 'name' => 'Primer Line', 'is_active' => true]);
    $secA2 = Section::factory()->create(['department_id' => $deptA->id, 'code' => 'PNT-S2', 'name' => 'Top Coat', 'is_active' => true]);

    $manager = User::factory()->manager($deptA->id)->create();

    $response = $this->actingAs($manager)->get(route('analytics.comparison', [
        'base_period' => '2026-09',
        'compare_period' => '2026-08',
        'type' => 'mom',
    ]));

    $response->assertOk();
    $data = $response->json();

    expect($data['scope']['department_id'])->toBe($deptA->id);
    expect($data['scope']['is_manager_scoped'])->toBeTrue();

    // Benchmarks should contain sections of Dept A
    $benchmarkNames = array_column($data['department_benchmarks'], 'name');
    expect($benchmarkNames)->toContain('Primer Line');
    expect($benchmarkNames)->toContain('Top Coat');
});

test('comparison supports all 4 comparison types: yoy, mom, qoq, and department', function () {
    $admin = User::factory()->admin()->create();
    Department::factory()->create(['name' => 'Machining Dept', 'is_active' => true]);

    $types = ['yoy', 'mom', 'qoq', 'department'];

    foreach ($types as $type) {
        $response = $this->actingAs($admin)->get(route('analytics.comparison', [
            'type' => $type,
            'base_period' => '2026-09',
        ]));

        $response->assertOk();
        expect($response->json('comparison_type'))->toBe($type);
        expect($response->json('period_comparison_chart.labels'))->toBeArray();
        expect(count($response->json('period_comparison_chart.labels')))->toBeGreaterThan(0);
    }
});

test('analytics index loads comparison tab data when tab is comparison', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create(['name' => 'Quality Assurance', 'code' => 'QA-01', 'is_active' => true]);

    $response = $this->actingAs($admin)->get(route('analytics.index', ['tab' => 'comparison']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Analytics/Index')
        ->where('currentTab', 'comparison')
        ->has('comparisonData')
        ->where('comparisonData.comparison_type', 'yoy')
        ->has('comparisonData.kpi')
        ->has('comparisonData.department_benchmarks')
        ->has('comparisonData.performers')
        ->has('comparisonData.best_practice_cards')
    );
});

test('export endpoint streams csv and pdf for comparison tab', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create(['name' => 'Stamping Logistics', 'is_active' => true]);

    $responseCsv = $this->actingAs($admin)->get(route('analytics.export', [
        'format' => 'csv',
        'tab' => 'comparison',
        'department_id' => $dept->id,
        'base_period' => '2026-09',
        'compare_period' => '2025-09',
        'type' => 'yoy',
    ]));

    $responseCsv->assertOk();
    $responseCsv->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $responsePdf = $this->actingAs($admin)->get(route('analytics.export', [
        'format' => 'pdf',
        'tab' => 'comparison',
        'department_id' => $dept->id,
        'base_period' => '2026-09',
        'compare_period' => '2025-09',
        'type' => 'yoy',
    ]));

    $responsePdf->assertOk();
    $responsePdf->assertHeader('content-type', 'application/pdf');
});
