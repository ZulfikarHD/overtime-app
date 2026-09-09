<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\PolicyThreshold;
use App\Models\Section;
use App\Models\SpklDocument;
use App\Models\User;
use Illuminate\Support\Carbon;

test('supervisory users receive employeeSummary prop on dashboard visit', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->has('employeeSummary')
        ->has('employeeSummary.items')
        ->has('employeeSummary.total_count')
        ->has('employeeSummary.fiscal_year')
        ->has('employeeSummary.fiscal_month')
        ->has('employeeSummary.soft_limit_hours')
        ->has('employeeSummary.scope')
    );
});

test('employee summary endpoint returns valid json structure for supervisory users', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->getJson(route('dashboard.employee-summary'));
    $response->assertOk();
    $response->assertJsonStructure([
        'items' => [
            '*' => [
                'id',
                'employee_id',
                'npk',
                'name',
                'full_name',
                'job_position',
                'department_id',
                'department_name',
                'section_id',
                'section_code',
                'section_name',
                'total_hours',
                'hours_production',
                'hours_tpm',
                'hours_project',
                'hours_others',
                'capex_hours',
                'opex_hours',
                'categories',
                'planned_hours',
                'burn_index',
                'burn_zone',
                'burn_zone_label',
                'burn_zone_color',
                'spkl_status',
                'spkl_status_label',
                'recent_shifts',
                'consecutive_alert',
                'consecutive_weeks',
                'weekly_hours',
                'weekly_limit_hours',
            ],
        ],
        'total_count',
        'fiscal_year',
        'fiscal_month',
        'month_name',
        'soft_limit_hours',
        'scope' => ['department_id', 'section_id'],
    ]);
});

test('manager is scoped strictly to employees within their assigned department', function () {
    $dept1 = Department::create([
        'code' => 'DEPT_SUMM_1',
        'name' => 'Assembly Dept',
        'cost_center_code' => 'CC-ASM-S1',
        'is_active' => true,
    ]);

    $dept2 = Department::create([
        'code' => 'DEPT_SUMM_2',
        'name' => 'Machining Dept',
        'cost_center_code' => 'CC-MCH-S2',
        'is_active' => true,
    ]);

    $sec1 = Section::create([
        'department_id' => $dept1->id,
        'code' => 'SEC_ASM1',
        'name' => 'Trim Line 1',
        'is_active' => true,
    ]);

    $sec2 = Section::create([
        'department_id' => $dept2->id,
        'code' => 'SEC_MCH1',
        'name' => 'CNC Line 1',
        'is_active' => true,
    ]);

    $emp1 = Employee::create([
        'npk' => 'EMP-S1-001',
        'department_id' => $dept1->id,
        'section_id' => $sec1->id,
        'full_name' => 'Budi Santoso',
        'job_position' => 'Assembler',
        'is_active' => true,
    ]);

    $emp2 = Employee::create([
        'npk' => 'EMP-S2-002',
        'department_id' => $dept2->id,
        'section_id' => $sec2->id,
        'full_name' => 'Joko Widodo',
        'job_position' => 'Machinist',
        'is_active' => true,
    ]);

    $manager = User::factory()->manager($dept1->id)->create();
    $this->actingAs($manager);

    $response = $this->getJson(route('dashboard.employee-summary'));
    $response->assertOk();

    $items = $response->json('items');
    $npks = collect($items)->pluck('npk')->all();

    expect($npks)->toContain($emp1->npk);
    expect($npks)->not->toContain($emp2->npk);
});

test('team leader is scoped strictly to employees within their assigned section', function () {
    $dept = Department::create([
        'code' => 'DEPT_TL_SEC',
        'name' => 'Welding Dept',
        'cost_center_code' => 'CC-WLD-TL',
        'is_active' => true,
    ]);

    $sec1 = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_WLD1',
        'name' => 'Sub-Assembly Weld',
        'is_active' => true,
    ]);

    $sec2 = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_WLD2',
        'name' => 'Main Body Weld',
        'is_active' => true,
    ]);

    $emp1 = Employee::create([
        'npk' => 'EMP-TL-001',
        'department_id' => $dept->id,
        'section_id' => $sec1->id,
        'full_name' => 'Ahmad Dahlan',
        'job_position' => 'Welder 1',
        'is_active' => true,
    ]);

    $emp2 = Employee::create([
        'npk' => 'EMP-TL-002',
        'department_id' => $dept->id,
        'section_id' => $sec2->id,
        'full_name' => 'Tri Sutrisno',
        'job_position' => 'Welder 2',
        'is_active' => true,
    ]);

    $teamLeader = User::factory()->teamLeader($sec1->id)->create([
        'department_id' => $dept->id,
    ]);
    $this->actingAs($teamLeader);

    $response = $this->getJson(route('dashboard.employee-summary'));
    $response->assertOk();

    $items = $response->json('items');
    $npks = collect($items)->pluck('npk')->all();

    expect($npks)->toContain($emp1->npk);
    expect($npks)->not->toContain($emp2->npk);
});

test('employee summary calculates accurate Burn Index, category distribution, and SPKL status', function () {
    $dept = Department::create([
        'code' => 'DEPT_CALC_TEST',
        'name' => 'Stamping Calc Dept',
        'cost_center_code' => 'CC-STP-CALC',
        'default_hourly_rate' => 40000,
        'is_active' => true,
    ]);

    $sec = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_STP1',
        'name' => 'Press Shop 1',
        'is_active' => true,
    ]);

    $now = Carbon::now('Asia/Jakarta');
    $year = $now->year;
    $month = $now->month;
    $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
    $date = "{$year}-{$monthStr}-05";

    OperationalCalendar::create([
        'calendar_date' => $date,
        'day_type' => 'HKN',
        'is_holiday' => false,
    ]);

    PolicyThreshold::create([
        'department_id' => $dept->id,
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
        'spkl_grace_period_days' => 2,
        'burn_warning_pct' => 100.0,
        'burn_danger_pct' => 115.0,
    ]);

    // Section budget: 100 hours planned for 2 active employees = 50 hours planned per employee
    OvertimeBudget::create([
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_hours' => 100.0,
        'planned_amount' => 4000000,
    ]);

    $emp1 = Employee::create([
        'npk' => 'EMP-CALC-001',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'full_name' => 'Fajar Nugroho',
        'job_position' => 'Die Setter',
        'is_active' => true,
    ]);

    $emp2 = Employee::create([
        'npk' => 'EMP-CALC-002',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'full_name' => 'Danang Prasetyo',
        'job_position' => 'Press Operator',
        'is_active' => true,
    ]);

    $manager = User::factory()->manager($dept->id)->create();

    // Overtime submission with attached SPKL
    $sub1 = OvertimeSubmission::create([
        'submission_code' => 'SPKL-CALC-001',
        'submission_date' => $date,
        'operational_date' => $date,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'submitted_by_user_id' => $manager->id,
        'status' => 'APPROVED',
        'total_hours_cached' => 25.0,
    ]);

    SpklDocument::create([
        'overtime_submission_id' => $sub1->id,
        'spkl_number' => 'SPKL/STP/2026/01',
        'status' => 'ATTACHED',
        'due_date' => $now->copy()->addDays(2)->toDateString(),
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub1->id,
        'employee_id' => $emp1->id,
        'npk_snapshot' => $emp1->npk,
        'hours_production' => 15.0,
        'hours_tpm' => 5.0,
        'hours_project' => 5.0, // CapEx
        'hours_others' => 0.0,
        'total_hours' => 25.0,
        'hourly_rate_snapshot' => 40000,
        'total_cost_snapshot' => 1000000,
        'status' => 'APPROVED',
    ]);

    $this->actingAs($manager);

    $response = $this->getJson(route('dashboard.employee-summary', ['date' => $date]));
    $response->assertOk();

    $items = collect($response->json('items'));
    $emp1Row = $items->firstWhere('npk', $emp1->npk);

    expect($emp1Row)->not->toBeNull();
    expect($emp1Row['total_hours'])->toEqual(25.0);
    expect($emp1Row['hours_production'])->toEqual(15.0);
    expect($emp1Row['hours_tpm'])->toEqual(5.0);
    expect($emp1Row['capex_hours'])->toEqual(5.0);
    expect($emp1Row['opex_hours'])->toEqual(20.0);
    // 25 hrs / 50 hrs planned = 50.0% Burn Index (Safe)
    expect($emp1Row['burn_index'])->toEqual(50.0);
    expect($emp1Row['burn_zone'])->toBe('safe');
    expect($emp1Row['spkl_status'])->toBe('approved');
    expect($emp1Row['recent_shifts'])->toHaveCount(1);
    expect($emp1Row['recent_shifts'][0]['spkl_number'])->toBe('SPKL/STP/2026/01');
});

test('line operator is forbidden from accessing employee summary endpoint and redirected on page visit', function () {
    $operator = User::factory()->create([
        'role' => 'user',
    ]);

    $this->actingAs($operator);

    // Page visit redirects to my.dashboard
    $pageResponse = $this->get(route('dashboard'));
    $pageResponse->assertRedirect(route('my.dashboard'));

    // JSON endpoint returns 403 Forbidden
    $jsonResponse = $this->getJson(route('dashboard.employee-summary'));
    $jsonResponse->assertStatus(403);
});

test('unauthenticated user is redirected to login', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));

    $jsonResponse = $this->getJson(route('dashboard.employee-summary'));
    $jsonResponse->assertStatus(401);
});
