<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use App\Services\EmployeeReportService;
use Inertia\Testing\AssertableInertia as Assert;

function ensureReportCalendarDate(string $date, string $dayType = 'HKN'): void
{
    if (! OperationalCalendar::whereDate('calendar_date', $date)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $date,
            'day_type' => $dayType,
            'is_holiday' => false,
        ]);
    }
}

test('team leader can search employees within their assigned section only', function () {
    $dept = Department::factory()->create(['name' => 'Assembly Department']);
    $sectionA = Section::factory()->create(['department_id' => $dept->id, 'name' => 'Chassis Line']);
    $sectionB = Section::factory()->create(['department_id' => $dept->id, 'name' => 'Engine Line']);

    $empInSec = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $sectionA->id,
        'npk' => 'EMP-10001',
        'full_name' => 'Budi Santoso',
    ]);

    $empOtherSec = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $sectionB->id,
        'npk' => 'EMP-20002',
        'full_name' => 'Budi Haryanto',
    ]);

    $tl = User::factory()->teamLeader($sectionA->id, $dept->id)->create();

    $response = $this->actingAs($tl)->getJson(route('reports.employees.search', ['q' => 'Budi']));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['npk'])->toBe('EMP-10001')
        ->and($data[0]['name'])->toBe('Budi Santoso');
});

test('manager can search employees within their department across sections', function () {
    $deptA = Department::factory()->create(['name' => 'Body & Stamping']);
    $deptB = Department::factory()->create(['name' => 'Quality Control']);

    $secA1 = Section::factory()->create(['department_id' => $deptA->id, 'name' => 'Stamping Line 1']);
    $secA2 = Section::factory()->create(['department_id' => $deptA->id, 'name' => 'Welding Line 2']);
    $secB1 = Section::factory()->create(['department_id' => $deptB->id, 'name' => 'Incoming Inspection']);

    Employee::factory()->create([
        'department_id' => $deptA->id,
        'section_id' => $secA1->id,
        'npk' => 'EMP-A101',
        'full_name' => 'Agus Priyono',
    ]);

    Employee::factory()->create([
        'department_id' => $deptA->id,
        'section_id' => $secA2->id,
        'npk' => 'EMP-A102',
        'full_name' => 'Agus Salim',
    ]);

    Employee::factory()->create([
        'department_id' => $deptB->id,
        'section_id' => $secB1->id,
        'npk' => 'EMP-B201',
        'full_name' => 'Agus QC',
    ]);

    $manager = User::factory()->manager($deptA->id)->create();

    $response = $this->actingAs($manager)->getJson(route('reports.employees.search', ['q' => 'Agus']));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(2);

    $npks = array_column($data, 'npk');
    expect($npks)->toContain('EMP-A101', 'EMP-A102')
        ->and($npks)->not->toContain('EMP-B201');
});

test('admin can search all employees plant-wide', function () {
    $deptA = Department::factory()->create();
    $secA = Section::factory()->create(['department_id' => $deptA->id]);

    $deptB = Department::factory()->create();
    $secB = Section::factory()->create(['department_id' => $deptB->id]);

    Employee::factory()->create([
        'department_id' => $deptA->id,
        'section_id' => $secA->id,
        'npk' => 'ISZ-9001',
        'full_name' => 'Dewi Sartika',
    ]);

    Employee::factory()->create([
        'department_id' => $deptB->id,
        'section_id' => $secB->id,
        'npk' => 'ISZ-9002',
        'full_name' => 'Dewi Lestari',
    ]);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->getJson(route('reports.employees.search', ['q' => 'Dewi']));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(2);
});

test('search query with less than 3 characters returns empty array without querying', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->getJson(route('reports.employees.search', ['q' => 'De']));

    $response->assertOk();
    expect($response->json('data'))->toBe([]);
});

test('regular user role cannot search other employees', function () {
    $user = User::factory()->user()->create();

    $response = $this->actingAs($user)->getJson(route('reports.employees.search', ['q' => 'Budi']));

    $response->assertForbidden();
});

test('team leader can view dossier of subordinate in their section', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);

    $emp = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'npk' => 'EMP-TL-01',
        'full_name' => 'Joko Widodo',
        'job_position' => 'Line Operator',
        'is_active' => true,
    ]);

    $tl = User::factory()->teamLeader($section->id, $dept->id)->create();

    $response = $this->actingAs($tl)->get(route('reports.employees.show', ['npk' => $emp->npk]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('reports/EmployeeDossier')
        ->where('employee.npk', 'EMP-TL-01')
        ->where('employee.full_name', 'Joko Widodo')
        ->where('employee.job_position', 'Line Operator')
        ->where('employee.is_active', true)
        ->has('fiscal_year')
        ->has('fiscal_month')
    );
});

test('team leader cannot view dossier of employee from another section', function () {
    $dept = Department::factory()->create();
    $sectionA = Section::factory()->create(['department_id' => $dept->id]);
    $sectionB = Section::factory()->create(['department_id' => $dept->id]);

    $emp = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $sectionB->id,
        'npk' => 'EMP-TL-02',
    ]);

    $tl = User::factory()->teamLeader($sectionA->id, $dept->id)->create();

    $response = $this->actingAs($tl)->get(route('reports.employees.show', ['npk' => $emp->npk]));

    $response->assertForbidden();
});

test('manager can view dossier of employee in their department but not outside', function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();

    $secA = Section::factory()->create(['department_id' => $deptA->id]);
    $secB = Section::factory()->create(['department_id' => $deptB->id]);

    $empA = Employee::factory()->create(['department_id' => $deptA->id, 'section_id' => $secA->id, 'npk' => 'EMP-MGR-A']);
    $empB = Employee::factory()->create(['department_id' => $deptB->id, 'section_id' => $secB->id, 'npk' => 'EMP-MGR-B']);

    $manager = User::factory()->manager($deptA->id)->create();

    // Within department: allowed
    $responseA = $this->actingAs($manager)->get(route('reports.employees.show', ['npk' => $empA->npk]));
    $responseA->assertOk();

    // Outside department: forbidden
    $responseB = $this->actingAs($manager)->get(route('reports.employees.show', ['npk' => $empB->npk]));
    $responseB->assertForbidden();
});

test('regular user can only view their own dossier', function () {
    $dept = Department::factory()->create();
    $sec = Section::factory()->create(['department_id' => $dept->id]);

    $empOwn = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'npk' => 'EMP-USER-OWN',
        'full_name' => 'Operator Sendiri',
    ]);

    $empOther = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'npk' => 'EMP-USER-OTHER',
        'full_name' => 'Operator Lain',
    ]);

    $user = User::factory()->user()->create([
        'npk' => 'EMP-USER-OWN',
    ]);

    // Own dossier: allowed
    $responseOwn = $this->actingAs($user)->get(route('reports.employees.show', ['npk' => $empOwn->npk]));
    $responseOwn->assertOk();
    $responseOwn->assertInertia(fn (Assert $page) => $page
        ->component('reports/EmployeeDossier')
        ->where('employee.npk', 'EMP-USER-OWN')
        ->where('is_own_dossier', true)
    );

    // Other employee dossier: forbidden
    $responseOther = $this->actingAs($user)->get(route('reports.employees.show', ['npk' => $empOther->npk]));
    $responseOther->assertForbidden();
});

test('visiting dossier index redirects regular user to their own dossier', function () {
    $user = User::factory()->user()->create([
        'npk' => 'EMP-USER-777',
    ]);

    $response = $this->actingAs($user)->get(route('reports.employees.index'));

    $response->assertRedirect(route('reports.employees.show', ['npk' => 'EMP-USER-777']));
});

test('team leader visiting dossier index sees roster scoped to their section', function () {
    $dept = Department::factory()->create();
    $secA = Section::factory()->create(['department_id' => $dept->id]);
    $secB = Section::factory()->create(['department_id' => $dept->id]);

    Employee::factory()->count(3)->create(['department_id' => $dept->id, 'section_id' => $secA->id]);
    Employee::factory()->count(2)->create(['department_id' => $dept->id, 'section_id' => $secB->id]);

    $tl = User::factory()->teamLeader($secA->id, $dept->id)->create();

    $response = $this->actingAs($tl)->get(route('reports.employees.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('reports/EmployeeDossier')
        ->where('employee', null)
        ->has('roster', 3)
    );
});

test('getSummary returns correct current month, YTD hours, and cost from approved items only', function () {
    ensureReportCalendarDate('2026-09-05', 'HKN');
    ensureReportCalendarDate('2026-09-12', 'HLR');
    ensureReportCalendarDate('2026-09-20', 'HKN');
    ensureReportCalendarDate('2026-08-15', 'HKN');

    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $submitter = User::factory()->teamLeader($section->id, $dept->id)->create();

    $emp = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'npk' => 'EMP-TEST-SUMM',
        'full_name' => 'Budi Worker',
        'is_active' => true,
    ]);

    // Submission 1: Sep 2026, HKN, Approved (4h prod, 2h tpm = 6h, cost 300,000)
    $sub1 = OvertimeSubmission::create([
        'submission_code' => 'OT-SUB-001',
        'submission_date' => '2026-09-05',
        'operational_date' => '2026-09-05',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $submitter->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub1->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 4.0,
        'hours_tpm' => 2.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 300000.0,
        'status' => 'APPROVED',
    ]);

    // Submission 2: Sep 2026, HLR, Approved (5h project, 1h others = 6h, cost 300,000)
    $sub2 = OvertimeSubmission::create([
        'submission_code' => 'OT-SUB-002',
        'submission_date' => '2026-09-12',
        'operational_date' => '2026-09-12',
        'day_type' => 'HLR',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $submitter->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub2->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 5.0,
        'hours_others' => 1.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 300000.0,
        'status' => 'APPROVED',
    ]);

    // Submission 3: Sep 2026, REJECTED (8h, cost 400,000) - must be ignored
    $sub3 = OvertimeSubmission::create([
        'submission_code' => 'OT-SUB-003',
        'submission_date' => '2026-09-20',
        'operational_date' => '2026-09-20',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $submitter->id,
        'status' => 'REJECTED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub3->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 8.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 400000.0,
        'status' => 'REJECTED',
    ]);

    // Submission 4: Earlier month (Aug 2026) - contributes to YTD, not current month
    $subAug = OvertimeSubmission::create([
        'submission_code' => 'OT-SUB-AUG',
        'submission_date' => '2026-08-15',
        'operational_date' => '2026-08-15',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $submitter->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $subAug->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 10.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 500000.0,
        'status' => 'APPROVED',
    ]);

    /** @var EmployeeReportService $service */
    $service = app(EmployeeReportService::class);
    $summary = $service->getSummary($emp->id, 2026, 9);

    expect($summary['current_month_hours'])->toBe(12.0)
        ->and($summary['ytd_hours'])->toBe(22.0)
        ->and($summary['total_cost_idr'])->toBe(600000.0)
        ->and($summary['category_breakdown']['production'])->toBe(4.0)
        ->and($summary['category_breakdown']['tpm'])->toBe(2.0)
        ->and($summary['category_breakdown']['project'])->toBe(5.0)
        ->and($summary['category_breakdown']['others'])->toBe(1.0)
        ->and($summary['day_type_breakdown']['hkn_hours'])->toBe(6.0)
        ->and($summary['day_type_breakdown']['hlr_hours'])->toBe(6.0)
        ->and($summary['day_type_breakdown']['hkn_pct'])->toBe(50.0)
        ->and($summary['day_type_breakdown']['hlr_pct'])->toBe(50.0);
});

test('individual burn index calculates based on section budget and active section employee count', function () {
    ensureReportCalendarDate('2026-09-10', 'HKN');

    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $submitter = User::factory()->teamLeader($section->id, $dept->id)->create();

    $emp1 = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'is_active' => true,
    ]);

    $emp2 = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'is_active' => true,
    ]);

    // Section budget: 100 planned hours for 2 active employees = 50 planned hours each
    OvertimeBudget::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_hours' => 100.0,
        'planned_cost_idr' => 5000000.0,
    ]);

    $sub = OvertimeSubmission::create([
        'submission_code' => 'OT-BBI-001',
        'submission_date' => '2026-09-10',
        'operational_date' => '2026-09-10',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $submitter->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $emp1->id,
        'npk_snapshot' => $emp1->npk,
        'hours_production' => 40.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 2000000.0,
        'status' => 'APPROVED',
    ]);

    /** @var EmployeeReportService $service */
    $service = app(EmployeeReportService::class);
    $summary = $service->getSummary($emp1->id, 2026, 9);

    expect($summary['individual_planned_hours'])->toBe(50.0)
        ->and($summary['burn_index'])->toBe(80.0);

    // When no budget is set for a month, burn index is null
    $summaryNoBudget = $service->getSummary($emp1->id, 2026, 10);
    expect($summaryNoBudget['burn_index'])->toBeNull()
        ->and($summaryNoBudget['individual_planned_hours'])->toBeNull();
});

test('departmental section ranking correctly ranks employees by approved hours', function () {
    ensureReportCalendarDate('2026-09-08', 'HKN');

    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $submitter = User::factory()->teamLeader($section->id, $dept->id)->create();

    $empA = Employee::factory()->create(['department_id' => $dept->id, 'section_id' => $section->id]);
    $empB = Employee::factory()->create(['department_id' => $dept->id, 'section_id' => $section->id]);
    $empC = Employee::factory()->create(['department_id' => $dept->id, 'section_id' => $section->id]);

    $sub = OvertimeSubmission::create([
        'submission_code' => 'OT-RNK-001',
        'submission_date' => '2026-09-08',
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $submitter->id,
        'status' => 'APPROVED',
    ]);

    // B has 35h, A has 20h, C has 10h
    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $empB->id,
        'npk_snapshot' => $empB->npk,
        'hours_production' => 35.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 1750000.0,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $empA->id,
        'npk_snapshot' => $empA->npk,
        'hours_production' => 20.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 1000000.0,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $empC->id,
        'npk_snapshot' => $empC->npk,
        'hours_production' => 10.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 500000.0,
        'status' => 'APPROVED',
    ]);

    /** @var EmployeeReportService $service */
    $service = app(EmployeeReportService::class);

    $summaryB = $service->getSummary($empB->id, 2026, 9);
    expect($summaryB['dept_rank']['rank'])->toBe(1)
        ->and($summaryB['dept_rank']['total_employees'])->toBe(3);

    $summaryA = $service->getSummary($empA->id, 2026, 9);
    expect($summaryA['dept_rank']['rank'])->toBe(2)
        ->and($summaryA['dept_rank']['total_employees'])->toBe(3);

    $summaryC = $service->getSummary($empC->id, 2026, 9);
    expect($summaryC['dept_rank']['rank'])->toBe(3)
        ->and($summaryC['dept_rank']['total_employees'])->toBe(3);
});

test('show dossier inertia response includes summary prop with all KPI and breakdown data', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $tl = User::factory()->teamLeader($section->id, $dept->id)->create();

    $emp = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'npk' => 'EMP-SHOW-PROP',
        'full_name' => 'Wayan Operator',
    ]);

    $response = $this->actingAs($tl)->get(route('reports.employees.show', [
        'npk' => $emp->npk,
        'year' => 2026,
        'month' => 9,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('reports/EmployeeDossier')
        ->where('employee.npk', 'EMP-SHOW-PROP')
        ->has('summary.current_month_hours')
        ->has('summary.ytd_hours')
        ->has('summary.dept_rank')
        ->has('summary.category_breakdown')
        ->has('summary.day_type_breakdown')
        ->has('summary.total_cost_idr')
        ->has('peer_comparison.section_average_hours')
        ->has('peer_comparison.variance_hours')
        ->has('peer_comparison.distribution')
        ->has('peer_comparison.top_5')
        ->has('peer_comparison.bottom_5')
    );
});

test('getPeerComparison calculates correct section average, individual hours, and CALC-06 variance', function () {
    ensureReportCalendarDate('2026-09-10', 'HKN');

    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id, 'name' => 'Sub-Assembly 1', 'code' => 'SEC-SUB1']);
    $submitter = User::factory()->teamLeader($section->id, $dept->id)->create();

    $empA = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Aditya Overloaded',
        'npk' => 'EMP-PEER-A',
        'is_active' => true,
    ]);

    $empB = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Bagus Balanced',
        'npk' => 'EMP-PEER-B',
        'is_active' => true,
    ]);

    $empC = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Candra Rested',
        'npk' => 'EMP-PEER-C',
        'is_active' => true,
    ]);

    $sub = OvertimeSubmission::create([
        'submission_code' => 'OT-PEER-001',
        'submission_date' => '2026-09-10',
        'operational_date' => '2026-09-10',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $submitter->id,
        'status' => 'APPROVED',
    ]);

    // Emp A: 30.0 hours
    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $empA->id,
        'npk_snapshot' => $empA->npk,
        'hours_production' => 30.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 1500000.0,
        'status' => 'APPROVED',
    ]);

    // Emp B: 15.0 hours
    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $empB->id,
        'npk_snapshot' => $empB->npk,
        'hours_production' => 15.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 750000.0,
        'status' => 'APPROVED',
    ]);

    // Emp C: 0.0 hours (no approved items)

    /** @var EmployeeReportService $service */
    $service = app(EmployeeReportService::class);

    // Test for Emp A (overloaded: +15.0 above section average)
    $peerA = $service->getPeerComparison($empA->id, $section->id, 2026, 9, false);
    expect($peerA['has_section'])->toBeTrue()
        ->and($peerA['total_section_employees'])->toBe(3)
        ->and($peerA['section_total_hours'])->toBe(45.0)
        ->and($peerA['section_average_hours'])->toBe(15.0)
        ->and($peerA['individual_hours'])->toBe(30.0)
        ->and($peerA['variance_hours'])->toBe(15.0)
        ->and($peerA['variance_status'])->toBe('above')
        ->and($peerA['distribution'])->toHaveCount(3)
        ->and($peerA['distribution'][0]['name'])->toBe('Aditya Overloaded')
        ->and($peerA['distribution'][0]['rank'])->toBe(1)
        ->and($peerA['distribution'][0]['is_current_employee'])->toBeTrue()
        ->and($peerA['top_5'][0]['name'])->toBe('Aditya Overloaded')
        ->and($peerA['bottom_5'][0]['name'])->toBe('Candra Rested');

    // Test for Emp B (balanced: variance 0.0)
    $peerB = $service->getPeerComparison($empB->id, $section->id, 2026, 9, false);
    expect($peerB['individual_hours'])->toBe(15.0)
        ->and($peerB['variance_hours'])->toBe(0.0)
        ->and($peerB['variance_status'])->toBe('equal')
        ->and($peerB['distribution'][1]['is_current_employee'])->toBeTrue();

    // Test for Emp C (underloaded: -15.0 below section average)
    $peerC = $service->getPeerComparison($empC->id, $section->id, 2026, 9, false);
    expect($peerC['individual_hours'])->toBe(0.0)
        ->and($peerC['variance_hours'])->toBe(-15.0)
        ->and($peerC['variance_status'])->toBe('below')
        ->and($peerC['distribution'][2]['is_current_employee'])->toBeTrue();
});

test('getPeerComparison excludes rejected and pending overtime items', function () {
    ensureReportCalendarDate('2026-09-15', 'HKN');

    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $submitter = User::factory()->teamLeader($section->id, $dept->id)->create();

    $emp = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'npk' => 'EMP-STATUS-CK',
    ]);

    // Approved submission (5.0h)
    $subApproved = OvertimeSubmission::create([
        'submission_code' => 'OT-STAT-APP',
        'submission_date' => '2026-09-15',
        'operational_date' => '2026-09-15',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $submitter->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $subApproved->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 5.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 250000.0,
        'status' => 'APPROVED',
    ]);

    // Rejected submission (20.0h)
    $subRejected = OvertimeSubmission::create([
        'submission_code' => 'OT-STAT-REJ',
        'submission_date' => '2026-09-15',
        'operational_date' => '2026-09-15',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $submitter->id,
        'status' => 'REJECTED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $subRejected->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 20.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 1000000.0,
        'status' => 'REJECTED',
    ]);

    /** @var EmployeeReportService $service */
    $service = app(EmployeeReportService::class);
    $result = $service->getPeerComparison($emp->id, $section->id, 2026, 9, false);

    expect($result['individual_hours'])->toBe(5.0)
        ->and($result['section_total_hours'])->toBe(5.0);
});

test('getPeerComparison strictly anonymizes co-workers for User role', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);

    $empTarget = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Operator Saya Sendiri',
        'npk' => 'EMP-ME-01',
    ]);

    $empPeer = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Rahasia Teman Seksi',
        'npk' => 'EMP-OTHER-02',
    ]);

    /** @var EmployeeReportService $service */
    $service = app(EmployeeReportService::class);

    // Non-anonymized (Supervisor)
    $supervisorResult = $service->getPeerComparison($empTarget->id, $section->id, 2026, 9, false);
    $peerItemSup = collect($supervisorResult['distribution'])->firstWhere('is_current_employee', false);
    expect($peerItemSup['name'])->toBe('Rahasia Teman Seksi')
        ->and($peerItemSup['npk'])->toBe('EMP-OTHER-02')
        ->and($peerItemSup['employee_id'])->toBe($empPeer->id);

    // Anonymized (Operator)
    $operatorResult = $service->getPeerComparison($empTarget->id, $section->id, 2026, 9, true);
    expect($operatorResult['is_anonymized'])->toBeTrue();

    // Target employee's own record retains their name
    $selfItem = collect($operatorResult['distribution'])->firstWhere('is_current_employee', true);
    expect($selfItem['name'])->toBe('Operator Saya Sendiri')
        ->and($selfItem['npk'])->toBe('EMP-ME-01');

    // Peer record is masked
    $peerItemAnon = collect($operatorResult['distribution'])->firstWhere('is_current_employee', false);
    expect($peerItemAnon['name'])->toMatch('/^Karyawan #\d+$/')
        ->and($peerItemAnon['npk'])->toBe('••••')
        ->and($peerItemAnon['employee_id'])->toBeNull();
});

test('getPeerComparison handles unassigned section id gracefully', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $emp = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'npk' => 'EMP-NO-SEC',
    ]);

    /** @var EmployeeReportService $service */
    $service = app(EmployeeReportService::class);
    $result = $service->getPeerComparison($emp->id, 0, 2026, 9, false);

    expect($result['has_section'])->toBeFalse()
        ->and($result['section_average_hours'])->toBe(0.0)
        ->and($result['variance_hours'])->toBe(0.0)
        ->and($result['distribution'])->toBeEmpty();
});
