<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\MonthlyBurnSnapshot;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\PolicyThreshold;
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

test('guest is redirected to login when accessing insights endpoints', function () {
    $this->get(route('analytics.insights'))->assertRedirect(route('login'));
    $this->post(route('analytics.action-items.status', ['id' => 'ACT-TEST-001']), ['status' => 'in_progress'])->assertRedirect(route('login'));
});

test('operator and team leader are forbidden from accessing insights endpoints', function () {
    $operator = User::factory()->user()->create();
    $teamLeader = User::factory()->teamLeader()->create();

    $this->actingAs($operator)
        ->get(route('analytics.insights'))
        ->assertForbidden();

    $this->actingAs($teamLeader)
        ->get(route('analytics.insights'))
        ->assertForbidden();

    $this->actingAs($operator)
        ->post(route('analytics.action-items.status', ['id' => 'ACT-TEST-001']), ['status' => 'in_progress'])
        ->assertForbidden();

    $this->actingAs($teamLeader)
        ->post(route('analytics.action-items.status', ['id' => 'ACT-TEST-001']), ['status' => 'in_progress'])
        ->assertForbidden();
});

test('admin can access insights endpoint and receives complete json structure', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create(['name' => 'Assembly Department', 'code' => 'ASY-01', 'is_active' => true]);
    $sec = Section::factory()->create(['department_id' => $dept->id, 'code' => 'ASY-SEC-01', 'name' => 'Trim Line', 'is_active' => true]);

    $response = $this->actingAs($admin)->get(route('analytics.insights'));

    $response->assertOk();
    $response->assertJsonStructure([
        'risk_indicators' => [
            'critical_count',
            'warning_count',
            'info_count',
            'total_risks',
            'all_normal',
            'items',
        ],
        'anomaly_detection' => [
            'labels',
            'dates',
            'daily_hours',
            'mean',
            'std_dev',
            'upper_band',
            'lower_band',
            'unusual_patterns_count',
            'summary',
            'anomalies',
        ],
        'action_items',
        'scope' => [
            'department_id',
            'department_name',
            'start_date',
            'end_date',
            'fiscal_year',
            'fiscal_month',
        ],
    ]);
});

test('budget overrun triggers risk indicator and action item', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create(['name' => 'Welding Department', 'code' => 'WLD-01', 'is_active' => true]);
    $sec = Section::factory()->create(['department_id' => $dept->id, 'code' => 'WLD-SEC-01', 'name' => 'Frame Welding', 'is_active' => true]);

    PolicyThreshold::factory()->create([
        'department_id' => $dept->id,
        'burn_warning_pct' => 100.0,
        'burn_danger_pct' => 115.0,
    ]);

    $now = Carbon::now('Asia/Jakarta');

    MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'fiscal_year' => (int) $now->year,
        'fiscal_month' => (int) $now->month,
        'planned_budget_hours' => 200.0,
        'cumulative_actual_hours' => 245.0,
        'cumulative_opex_hours' => 245.0,
        'cumulative_capex_hours' => 0.0,
        'burn_index_pct' => 122.5,
        'burn_velocity' => 1.25,
        'burn_zone' => 'ZONE_4_POOR',
    ]);

    $response = $this->actingAs($admin)->get(route('analytics.insights', [
        'department_id' => $dept->id,
    ]));

    $response->assertOk();
    $data = $response->json();

    expect($data['risk_indicators']['critical_count'])->toBeGreaterThanOrEqual(1);
    expect($data['risk_indicators']['all_normal'])->toBeFalse();

    $riskTitles = array_column($data['risk_indicators']['items'], 'title');
    expect(implode(' ', $riskTitles))->toContain('Frame Welding');

    $actionItems = $data['action_items'];
    expect($actionItems)->not->toBeEmpty();
    expect($actionItems[0]['priority'])->toBe('high');
});

test('anomaly detection computes statistical bands and flags unusual spikes', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create(['name' => 'Stamping Department', 'code' => 'STP-01', 'is_active' => true]);
    $sec = Section::factory()->create(['department_id' => $dept->id, 'code' => 'STP-SEC-01', 'name' => 'Press Line A', 'is_active' => true]);
    $emp = Employee::factory()->create(['department_id' => $dept->id, 'section_id' => $sec->id]);

    $now = Carbon::now('Asia/Jakarta');

    // Create a large spike on today's operational date
    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-INSIGHTS-SPIKE-001',
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
        'hours_production' => 50.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'total_hours' => 50.0,
        'hourly_rate_snapshot' => 45000.0,
        'total_cost_snapshot' => 2250000.0,
        'status' => 'APPROVED',
    ]);

    $response = $this->actingAs($admin)->get(route('analytics.insights', [
        'department_id' => $dept->id,
    ]));

    $response->assertOk();
    $data = $response->json();

    expect($data['anomaly_detection']['unusual_patterns_count'])->toBeGreaterThanOrEqual(1);
    expect($data['anomaly_detection']['mean'])->toBeGreaterThan(0.0);
    expect($data['anomaly_detection']['upper_band'])->toBeGreaterThan(0.0);
});

test('manager is scoped to their department on insights endpoint', function () {
    $deptA = Department::factory()->create(['name' => 'Painting Department', 'code' => 'PNT-01', 'is_active' => true]);
    $deptB = Department::factory()->create(['name' => 'Chassis Department', 'code' => 'CHS-01', 'is_active' => true]);

    $managerA = User::factory()->manager($deptA->id)->create();

    $response = $this->actingAs($managerA)->get(route('analytics.insights', [
        'department_id' => $deptB->id, // Attempt to query foreign department
    ]));

    $response->assertOk();
    $data = $response->json();

    expect($data['scope']['department_id'])->toBe($deptA->id);
    expect($data['scope']['department_name'])->toBe($deptA->name);
});

test('manager can update action item status with resolution note', function () {
    $manager = User::factory()->manager()->create();
    $actionItemId = 'ACT-BUDGET-999-2026-9';

    $response = $this->actingAs($manager)->postJson(route('analytics.action-items.status', ['id' => $actionItemId]), [
        'status' => 'in_progress',
        'resolution_note' => 'Penyesuaian kuota lembur shift 2 telah dibahas pada standup.',
    ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'action_item' => [
            'id' => $actionItemId,
            'status' => 'in_progress',
            'resolution_note' => 'Penyesuaian kuota lembur shift 2 telah dibahas pada standup.',
        ],
    ]);

    $manager->refresh();
    expect($manager->preferences['action_items'][$actionItemId]['status'])->toBe('in_progress');
    expect($manager->preferences['action_items'][$actionItemId]['resolution_note'])->toBe('Penyesuaian kuota lembur shift 2 telah dibahas pada standup.');
});

test('action item status update fails on invalid status', function () {
    $manager = User::factory()->manager()->create();

    $response = $this->actingAs($manager)->postJson(route('analytics.action-items.status', ['id' => 'ACT-001']), [
        'status' => 'invalid_status_value',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['status']);
});

test('export endpoint streams csv for insights tab', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create(['name' => 'Machining Department', 'is_active' => true]);

    $response = $this->actingAs($admin)->get(route('analytics.export', [
        'format' => 'csv',
        'tab' => 'insights',
        'department_id' => $dept->id,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
    ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

test('analytics hub index renders insights tab with initial data', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('analytics.index', ['tab' => 'insights']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Analytics/Index')
        ->where('currentTab', 'insights')
        ->has('insightsData')
        ->has('insightsData.risk_indicators')
        ->has('insightsData.anomaly_detection')
        ->has('insightsData.action_items')
    );
});
