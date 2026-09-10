<?php

use App\Models\Department;
use App\Models\Employee;
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

test('guest is redirected to login when accessing scenario endpoints', function () {
    $this->get(route('analytics.scenario'))->assertRedirect(route('login'));
    $this->post(route('analytics.scenario.calculate'), [])->assertRedirect(route('login'));
    $this->post(route('analytics.scenario.save'), [])->assertRedirect(route('login'));
    $this->delete(route('analytics.scenario.destroy', ['id' => 'scen_123']))->assertRedirect(route('login'));
});

test('operator and team leader are forbidden from accessing scenario endpoints', function () {
    $operator = User::factory()->user()->create();
    $teamLeader = User::factory()->teamLeader()->create();

    $this->actingAs($operator)
        ->get(route('analytics.scenario'))
        ->assertForbidden();

    $this->actingAs($teamLeader)
        ->get(route('analytics.scenario'))
        ->assertForbidden();

    $this->actingAs($operator)
        ->post(route('analytics.scenario.calculate'), [
            'type' => 'builder',
            'overtime_change_pct' => 10,
        ])
        ->assertForbidden();

    $this->actingAs($teamLeader)
        ->post(route('analytics.scenario.save'), [
            'name' => 'Forbidden Scenario',
            'overtime_change_pct' => 10,
            'projected_hours' => 500,
            'projected_cost' => 25000000,
            'projected_burn_index' => 95,
            'safety_risk_score' => 15,
            'production_volume_impact_pct' => 7.8,
        ])
        ->assertForbidden();
});

test('admin can access scenario endpoint and receives complete json structure', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create(['name' => 'Assembly Department', 'code' => 'ASY-01', 'is_active' => true]);
    $sec = Section::factory()->create(['department_id' => $dept->id, 'code' => 'ASY-SEC-01', 'name' => 'Trim Line', 'is_active' => true]);
    $emp = Employee::factory()->create(['department_id' => $dept->id, 'section_id' => $sec->id, 'hourly_rate' => 50000]);

    PolicyThreshold::factory()->create([
        'department_id' => null,
        'weekly_soft_limit_hours' => 20.0,
    ]);

    $now = Carbon::now('Asia/Jakarta');

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-TEST-SCEN-001',
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
        'hours_production' => 3.0,
        'hours_tpm' => 1.0,
        'hours_project' => 0.5,
        'hours_others' => 0.5,
        'hourly_rate_snapshot' => 50000,
        'total_cost_snapshot' => 250000,
        'total_hours' => 5.0,
        'status' => 'APPROVED',
    ]);

    $response = $this->actingAs($admin)->get(route('analytics.scenario'));

    $response->assertOk();
    $response->assertJsonStructure([
        'baseline' => [
            'department_id',
            'department_name',
            'actual_hours',
            'actual_cost',
            'formatted_actual_cost',
            'budget_cost',
            'formatted_budget_cost',
            'budget_hours',
            'burn_index_pct',
            'active_headcount',
            'avg_hourly_rate',
            'formatted_avg_hourly_rate',
            'safety_risk_score',
        ],
        'sections' => [
            '*' => [
                'id',
                'code',
                'name',
                'department_id',
                'labor_factor',
                'historical_hours',
                'historical_units',
                'category_ratios',
            ],
        ],
        'departments',
        'policy' => [
            'weekly_soft_limit_hours',
            'consecutive_weeks_alert',
        ],
        'correlation_r',
        'saved_scenarios',
        'initial_calculator_result',
        'initial_builder_result',
        'scope',
    ]);
});

test('manager sees scoped scenario data for their own department', function () {
    $deptA = Department::factory()->create(['name' => 'Stamping Department', 'code' => 'STP-01', 'is_active' => true]);
    $deptB = Department::factory()->create(['name' => 'Painting Department', 'code' => 'PNT-01', 'is_active' => true]);

    $manager = User::factory()->manager($deptA->id)->create();

    $secA = Section::factory()->create(['department_id' => $deptA->id, 'name' => 'Press Line 1', 'is_active' => true]);
    $secB = Section::factory()->create(['department_id' => $deptB->id, 'name' => 'Paint Booth 2', 'is_active' => true]);

    $response = $this->actingAs($manager)->get(route('analytics.scenario'));

    $response->assertOk();
    $data = $response->json();

    expect($data['scope']['department_id'])->toBe($deptA->id);
    expect($data['sections'])->each(fn ($sec) => $sec->department_id->toBe($deptA->id));
});

test('production planning calculator computes correct resource estimates and category breakdown', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create(['name' => 'Assembly', 'default_hourly_rate' => 50000]);
    $sec = Section::factory()->create(['department_id' => $dept->id, 'name' => 'Final Line']);

    PolicyThreshold::factory()->create([
        'department_id' => null,
        'weekly_soft_limit_hours' => 20.0,
    ]);

    $response = $this->actingAs($admin)->postJson(route('analytics.scenario.calculate'), [
        'type' => 'planning',
        'target_volume' => 2000,
        'period' => 'monthly',
        'section_id' => $sec->id,
    ]);

    $response->assertOk();
    $response->assertJson([
        'status' => 'success',
        'type' => 'planning',
    ]);

    $data = $response->json('data');
    expect($data['target_volume'])->toBe(2000);
    expect($data['period'])->toBe('monthly');
    expect($data['section_id'])->toBe($sec->id);
    expect($data['estimated_hours'])->toBeGreaterThan(0);
    expect($data['estimated_cost'])->toBeGreaterThan(0);
    expect($data['headcount_needed'])->toBeGreaterThan(0);
    expect($data['efficiency_pct'])->toBeGreaterThan(0);
    expect($data['categories'])->toHaveCount(4);

    $keys = array_column($data['categories'], 'key');
    expect($keys)->toContain('production', 'tpm', 'project', 'others');
});

test('scenario builder computes cost impact, burn index, and safety risk score', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create(['name' => 'Welding', 'default_hourly_rate' => 50000]);

    PolicyThreshold::factory()->create([
        'department_id' => null,
        'weekly_soft_limit_hours' => 20.0,
    ]);

    $response = $this->actingAs($admin)->postJson(route('analytics.scenario.calculate'), [
        'type' => 'builder',
        'overtime_change_pct' => 25.0,
        'budget_allocation' => 50000000,
        'department_id' => $dept->id,
    ]);

    $response->assertOk();
    $response->assertJson([
        'status' => 'success',
        'type' => 'builder',
    ]);

    $data = $response->json('data');
    expect((float) $data['overtime_change_pct'])->toBe(25.0);
    expect($data['projected_hours'])->toBeGreaterThan(0);
    expect($data['projected_cost'])->toBeGreaterThan(0);
    expect($data['production_volume_impact_pct'])->toBe(19.5); // 25 * 0.78
    expect($data['projected_burn_index'])->toBeGreaterThan(0);
    expect($data['safety_risk_score'])->toBeGreaterThanOrEqual(0);
});

test('scenario builder clamps overtime change between -50% and +50%', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->postJson(route('analytics.scenario.calculate'), [
        'type' => 'builder',
        'overtime_change_pct' => 75.0, // Invalid: exceeds max 50
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['overtime_change_pct']);

    $responseMin = $this->actingAs($admin)->postJson(route('analytics.scenario.calculate'), [
        'type' => 'builder',
        'overtime_change_pct' => -75.0, // Invalid: below min -50
    ]);

    $responseMin->assertStatus(422);
    $responseMin->assertJsonValidationErrors(['overtime_change_pct']);
});

test('user can save and delete named scenario in preferences', function () {
    $admin = User::factory()->admin()->create();

    $saveResponse = $this->actingAs($admin)->postJson(route('analytics.scenario.save'), [
        'name' => 'Skenario Peak Q4 2026',
        'department_id' => null,
        'overtime_change_pct' => 20.0,
        'budget_allocation' => 60000000,
        'projected_hours' => 540.0,
        'projected_cost' => 27000000,
        'projected_burn_index' => 90.0,
        'burn_zone' => 'on_track',
        'safety_risk_score' => 14.5,
        'production_volume_impact_pct' => 15.6,
    ]);

    $saveResponse->assertOk();
    $saveResponse->assertJson(['status' => 'success']);

    $saved = $saveResponse->json('saved_scenarios');
    expect($saved)->toHaveCount(1);
    expect($saved[0]['name'])->toBe('Skenario Peak Q4 2026');
    $savedId = $saved[0]['id'];

    // Verify stored in DB
    $admin->refresh();
    expect($admin->preferences['saved_scenarios'])->toHaveCount(1);

    // Delete scenario
    $deleteResponse = $this->actingAs($admin)->deleteJson(route('analytics.scenario.destroy', ['id' => $savedId]));
    $deleteResponse->assertOk();
    $deleteResponse->assertJson(['status' => 'success']);

    $admin->refresh();
    expect($admin->preferences['saved_scenarios'])->toHaveCount(0);
});

test('analytics index passes scenarioData prop when tab is scenario', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create(['name' => 'Assembly', 'is_active' => true]);
    Section::factory()->create(['department_id' => $dept->id, 'name' => 'Chassis', 'is_active' => true]);

    $response = $this->actingAs($admin)->get(route('analytics.index', ['tab' => 'scenario']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Analytics/Index')
        ->where('currentTab', 'scenario')
        ->has('scenarioData')
        ->where('scenarioData.scope.department_id', null)
        ->has('scenarioData.sections')
    );
});
