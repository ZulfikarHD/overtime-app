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

test('guest is redirected to login when accessing correlation endpoint', function () {
    $this->get(route('analytics.correlation'))->assertRedirect(route('login'));
});

test('operator and team leader are forbidden from accessing correlation endpoint', function () {
    $operator = User::factory()->user()->create();
    $teamLeader = User::factory()->teamLeader()->create();

    $this->actingAs($operator)
        ->get(route('analytics.correlation'))
        ->assertForbidden();

    $this->actingAs($teamLeader)
        ->get(route('analytics.correlation'))
        ->assertForbidden();
});

test('admin can access correlation endpoint and receives complete json structure', function () {
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
        'submission_code' => 'OT-TEST-CORR-001',
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
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000,
        'status' => 'APPROVED',
    ]);

    $response = $this->actingAs($admin)->get(route('analytics.correlation'));

    $response->assertOk();
    $response->assertJsonStructure([
        'kpi' => [
            'sweet_spot_min',
            'sweet_spot_max',
            'peak_efficiency_hours',
            'warning_threshold_hours',
            'current_weekly_avg_hours',
            'current_zone',
            'current_zone_label',
        ],
        'overtime_vs_production' => [
            'erp_connected',
            'message',
            'correlation_r',
            'r_squared',
            'regression' => [
                'slope',
                'intercept',
                'formula',
            ],
            'trend_line',
            'scatter_points',
            'sections',
        ],
        'overtime_vs_quality' => [
            'available',
            'message',
            'subtext',
            'correlation_r',
            'scatter_points',
        ],
        'optimal_zone_chart' => [
            'labels',
            'hours_series',
            'efficiency_series',
            'current_weekly_avg',
            'zones' => [
                'under_utilized',
                'sweet_spot',
                'over_threshold',
            ],
        ],
        'correlation_matrix' => [
            'variables',
            'matrix',
            'legend',
        ],
        'scope' => [
            'department_id',
            'department_name',
            'start_date',
            'end_date',
        ],
    ]);
});

test('manager is strictly scoped to assigned department in correlation data', function () {
    $deptA = Department::factory()->create(['name' => 'Department A', 'code' => 'D-A', 'is_active' => true]);
    $deptB = Department::factory()->create(['name' => 'Department B', 'code' => 'D-B', 'is_active' => true]);

    $secA = Section::factory()->create(['department_id' => $deptA->id, 'code' => 'SEC-A1', 'name' => 'Section A1', 'is_active' => true]);
    $secB = Section::factory()->create(['department_id' => $deptB->id, 'code' => 'SEC-B1', 'name' => 'Section B1', 'is_active' => true]);

    $managerA = User::factory()->manager($deptA->id)->create();

    // Manager requests department B, but backend forces department A
    $response = $this->actingAs($managerA)->get(route('analytics.correlation', ['department_id' => $deptB->id]));

    $response->assertOk();
    expect($response->json('scope.department_id'))->toBe($deptA->id)
        ->and($response->json('scope.department_name'))->toBe('Department A');

    $sectionIds = array_column($response->json('overtime_vs_production.sections'), 'id');
    expect($sectionIds)->toContain($secA->id)
        ->and($sectionIds)->not->toContain($secB->id);
});

test('handles erp disconnected state gracefully without 500 error', function () {
    config(['services.erp.connected' => false]);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('analytics.correlation'));

    $response->assertOk();
    $prod = $response->json('overtime_vs_production');
    expect($prod['erp_connected'])->toBeFalse()
        ->and($prod['message'])->toContain('ERP belum terhubung')
        ->and($prod['correlation_r'])->toBeNull();

    $quality = $response->json('overtime_vs_quality');
    expect($quality['available'])->toBeFalse()
        ->and($quality['message'])->toContain('ERP');
});

test('handles erp connected state returning real regression scatter', function () {
    config(['services.erp.connected' => true]);

    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create(['name' => 'Engine Plant', 'code' => 'ENG-01', 'is_active' => true]);
    $sec = Section::factory()->create(['department_id' => $dept->id, 'code' => 'ENG-SEC-01', 'name' => 'Machining', 'is_active' => true]);
    $emp = Employee::factory()->create(['department_id' => $dept->id, 'section_id' => $sec->id]);

    $now = Carbon::now('Asia/Jakarta');
    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-TEST-CORR-002',
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
        'hours_production' => 5.0,
        'status' => 'APPROVED',
    ]);

    $response = $this->actingAs($admin)->get(route('analytics.correlation'));

    $response->assertOk();
    $prod = $response->json('overtime_vs_production');
    expect($prod['erp_connected'])->toBeTrue()
        ->and($prod['correlation_r'])->not->toBeNull()
        ->and(count($prod['scatter_points']))->toBeGreaterThan(0)
        ->and(count($prod['trend_line']))->toBe(2);
});

test('inertia index view includes correlationData when tab is correlation', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('analytics.index', ['tab' => 'correlation']));

    $response->assertOk();
    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('Analytics/Index')
            ->where('currentTab', 'correlation')
            ->has('correlationData')
            ->has('correlationData.kpi')
            ->has('correlationData.overtime_vs_production')
            ->has('correlationData.overtime_vs_quality')
            ->has('correlationData.optimal_zone_chart')
            ->has('correlationData.correlation_matrix')
    );
});

test('csv export endpoint supports correlation tab', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('analytics.export', [
        'tab' => 'correlation',
        'format' => 'csv',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});
