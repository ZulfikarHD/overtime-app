<?php

use App\Models\CapexProject;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ExportLog;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-09-09 10:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

function makeSubmission(int $departmentId, ?int $sectionId = null, ?int $userId = null, string $date = '2026-09-05', string $code = 'OT-SUB-01'): OvertimeSubmission
{
    if (! OperationalCalendar::where('calendar_date', 'like', "{$date}%")->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $date,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }

    if (! $sectionId) {
        $section = Section::firstOrCreate(
            ['department_id' => $departmentId, 'code' => 'SEC-DEF-'.$departmentId],
            ['name' => 'Default Section']
        );
        $sectionId = $section->id;
    }

    if (! $userId) {
        $user = User::factory()->admin()->create();
        $userId = $user->id;
    }

    return OvertimeSubmission::create([
        'submission_code' => $code,
        'submission_date' => $date,
        'operational_date' => $date,
        'day_type' => 'HKN',
        'department_id' => $departmentId,
        'section_id' => $sectionId,
        'submitted_by_user_id' => $userId,
        'status' => 'APPROVED',
    ]);
}

test('guest is redirected to login when accessing capex attribution report', function () {
    $this->get(route('admin.capex-projects.index', ['tab' => 'attribution']))
        ->assertRedirect(route('login'));

    $this->get(route('admin.capex-projects.export-attribution'))
        ->assertRedirect(route('login'));
});

test('standard user cannot access capex attribution report or export', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user)
        ->get(route('admin.capex-projects.index', ['tab' => 'attribution']))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.capex-projects.export-attribution'))
        ->assertForbidden();
});

test('legacy capex labor report route redirects to admin hub with tab=attribution and preserves query parameters', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->get('/reports/capex-labor?search=WELD&date_from=2026-09-01');

    $response->assertRedirect('/admin/capex-projects?search=WELD&date_from=2026-09-01&tab=attribution');
});

test('admin can view capex attribution report on tab=attribution with project grouping and subtotals', function () {
    $dept = Department::factory()->create(['code' => 'ASSY', 'name' => 'Assembly Dept']);
    $sec = Section::factory()->create(['department_id' => $dept->id, 'name' => 'Line 1']);

    $admin = User::factory()->admin()->create();
    $reviewer = User::factory()->manager($dept->id)->create(['name' => 'Supervisor Hendra']);

    $emp1 = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'npk' => 'TECH-001',
        'full_name' => 'Budi Santoso',
    ]);
    $emp2 = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'npk' => 'TECH-002',
        'full_name' => 'Agus Priyono',
    ]);

    $projectA = CapexProject::factory()->create([
        'department_id' => $dept->id,
        'project_code' => 'CPX-2026-ASSY-001',
        'name' => 'Instalasi Robot Welding Line 1',
        'asset_code' => 'AST-8811',
        'status' => 'ACTIVE',
    ]);

    $projectB = CapexProject::factory()->create([
        'department_id' => $dept->id,
        'project_code' => 'CPX-2026-ASSY-002',
        'name' => 'Otomasi Feeder Stamping',
        'asset_code' => 'AST-8812',
        'status' => 'ACTIVE',
    ]);

    $submission = makeSubmission($dept->id, $sec->id, $admin->id, '2026-09-05', 'OT-SUB-20260905-01');

    // Item 1: Project A, Approved
    OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $emp1->id,
        'npk_snapshot' => $emp1->npk,
        'capex_project_id' => $projectA->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 4.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 200000.0,
        'status' => 'APPROVED',
        'reviewed_by_user_id' => $reviewer->id,
        'reviewed_at' => '2026-09-06 14:00:00',
    ]);

    // Item 2: Project A, Approved (second employee)
    OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $emp2->id,
        'npk_snapshot' => $emp2->npk,
        'capex_project_id' => $projectA->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 6.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 300000.0,
        'status' => 'APPROVED',
        'reviewed_by_user_id' => $reviewer->id,
        'reviewed_at' => '2026-09-06 14:00:00',
    ]);

    // Item 3: Project B, Approved
    OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $emp1->id,
        'npk_snapshot' => $emp1->npk,
        'capex_project_id' => $projectB->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 3.5,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 175000.0,
        'status' => 'APPROVED',
        'reviewed_by_user_id' => $reviewer->id,
        'reviewed_at' => '2026-09-06 14:00:00',
    ]);

    // Item 4: Project A, REJECTED (must NOT appear in attribution report)
    OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $emp1->id,
        'npk_snapshot' => $emp1->npk,
        'capex_project_id' => $projectA->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 5.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 250000.0,
        'status' => 'REJECTED',
        'reviewed_by_user_id' => $reviewer->id,
        'reviewed_at' => '2026-09-06 14:00:00',
    ]);

    // Item 5: Project A, PENDING (must NOT appear in attribution report)
    OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $emp2->id,
        'npk_snapshot' => $emp2->npk,
        'capex_project_id' => $projectA->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 2.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 100000.0,
        'status' => 'PENDING',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.capex-projects.index', ['tab' => 'attribution']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/CapexProjects/Index')
            ->where('activeTab', 'attribution')
            ->has('attribution', fn (Assert $attr) => $attr
                ->where('grand_total_hours', 13.5)
                ->where('grand_total_cost', 675000)
                ->where('total_items', 3)
                ->where('total_projects', 2)
                ->has('groups', 2)
                // Project A Group assertions
                ->where('groups.0.project_code', 'CPX-2026-ASSY-001')
                ->where('groups.0.subtotal_hours', fn ($val) => (float) $val === 10.0)
                ->where('groups.0.subtotal_cost', fn ($val) => (float) $val === 500000.0)
                ->where('groups.0.item_count', 2)
                // Project B Group assertions
                ->where('groups.1.project_code', 'CPX-2026-ASSY-002')
                ->where('groups.1.subtotal_hours', fn ($val) => (float) $val === 3.5)
                ->where('groups.1.subtotal_cost', fn ($val) => (float) $val === 175000.0)
                ->where('groups.1.item_count', 1)
            )
            ->has('capexProjectsList', 2)
        );
});

test('department manager is strictly scoped to their own department projects', function () {
    $deptA = Department::factory()->create(['code' => 'ASSY', 'name' => 'Assembly Dept']);
    $deptB = Department::factory()->create(['code' => 'WELD', 'name' => 'Welding Dept']);

    $managerA = User::factory()->manager($deptA->id)->create();
    $admin = User::factory()->admin()->create();

    $projectA = CapexProject::factory()->create([
        'department_id' => $deptA->id,
        'project_code' => 'CPX-2026-ASSY-101',
        'status' => 'ACTIVE',
    ]);
    $projectB = CapexProject::factory()->create([
        'department_id' => $deptB->id,
        'project_code' => 'CPX-2026-WELD-201',
        'status' => 'ACTIVE',
    ]);

    $empA = Employee::factory()->create(['department_id' => $deptA->id]);
    $empB = Employee::factory()->create(['department_id' => $deptB->id]);

    $subA = makeSubmission($deptA->id, null, $admin->id, '2026-09-05', 'OT-SUB-A-01');
    $subB = makeSubmission($deptB->id, null, $admin->id, '2026-09-05', 'OT-SUB-B-01');

    OvertimeItem::create([
        'overtime_submission_id' => $subA->id,
        'employee_id' => $empA->id,
        'npk_snapshot' => $empA->npk,
        'capex_project_id' => $projectA->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 5.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 40000.0,
        'total_cost_snapshot' => 200000.0,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $subB->id,
        'employee_id' => $empB->id,
        'npk_snapshot' => $empB->npk,
        'capex_project_id' => $projectB->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 8.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 40000.0,
        'total_cost_snapshot' => 320000.0,
        'status' => 'APPROVED',
    ]);

    $this->actingAs($managerA)
        ->get(route('admin.capex-projects.index', ['tab' => 'attribution']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('attribution.total_projects', 1)
            ->where('attribution.total_items', 1)
            ->where('attribution.grand_total_hours', fn ($val) => (float) $val === 5.0)
            ->where('attribution.groups.0.project_code', 'CPX-2026-ASSY-101')
        );

    // Manager cannot export foreign department records
    $this->actingAs($managerA)
        ->get(route('admin.capex-projects.export-attribution', ['department_id' => $deptB->id]))
        ->assertForbidden();
});

test('attribution report filters correctly by project_id, date range, and search keyword', function () {
    $dept = Department::factory()->create();
    $admin = User::factory()->admin()->create();

    $projectA = CapexProject::factory()->create(['department_id' => $dept->id, 'project_code' => 'CPX-2026-FLT-001']);
    $projectB = CapexProject::factory()->create(['department_id' => $dept->id, 'project_code' => 'CPX-2026-FLT-002']);

    $emp1 = Employee::factory()->create(['department_id' => $dept->id, 'npk' => 'ISZ-9001', 'full_name' => 'Charlie Chaplin']);
    $emp2 = Employee::factory()->create(['department_id' => $dept->id, 'npk' => 'ISZ-9002', 'full_name' => 'David Beckham']);

    $subAug = makeSubmission($dept->id, null, $admin->id, '2026-08-15', 'OT-AUG-01');
    $subSep = makeSubmission($dept->id, null, $admin->id, '2026-09-02', 'OT-SEP-01');

    // August item
    OvertimeItem::create([
        'overtime_submission_id' => $subAug->id,
        'employee_id' => $emp1->id,
        'npk_snapshot' => $emp1->npk,
        'capex_project_id' => $projectA->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 4.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 45000.0,
        'total_cost_snapshot' => 180000.0,
        'status' => 'APPROVED',
    ]);

    // September item for Project A
    OvertimeItem::create([
        'overtime_submission_id' => $subSep->id,
        'employee_id' => $emp2->id,
        'npk_snapshot' => $emp2->npk,
        'capex_project_id' => $projectA->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 7.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 45000.0,
        'total_cost_snapshot' => 315000.0,
        'status' => 'APPROVED',
    ]);

    // September item for Project B
    OvertimeItem::create([
        'overtime_submission_id' => $subSep->id,
        'employee_id' => $emp1->id,
        'npk_snapshot' => $emp1->npk,
        'capex_project_id' => $projectB->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 5.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 45000.0,
        'total_cost_snapshot' => 225000.0,
        'status' => 'APPROVED',
    ]);

    // Filter by project_id
    $this->actingAs($admin)
        ->get(route('admin.capex-projects.index', ['tab' => 'attribution', 'project_id' => $projectA->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('attribution.total_projects', 1)
            ->where('attribution.total_items', 2)
            ->where('attribution.grand_total_hours', fn ($val) => (float) $val === 11.0)
        );

    // Filter by date range (September only)
    $this->actingAs($admin)
        ->get(route('admin.capex-projects.index', [
            'tab' => 'attribution',
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('attribution.total_projects', 2)
            ->where('attribution.total_items', 2)
            ->where('attribution.grand_total_hours', fn ($val) => (float) $val === 12.0)
        );

    // Filter by search keyword (David Beckham / ISZ-9002)
    $this->actingAs($admin)
        ->get(route('admin.capex-projects.index', [
            'tab' => 'attribution',
            'search' => 'Beckham',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('attribution.total_projects', 1)
            ->where('attribution.total_items', 1)
            ->where('attribution.grand_total_hours', fn ($val) => (float) $val === 7.0)
        );
});

test('admin can export capex attribution report to xlsx and csv, writing audit export log', function () {
    $dept = Department::factory()->create(['code' => 'ASMB', 'name' => 'Assembly']);
    $admin = User::factory()->admin()->create();

    $project = CapexProject::factory()->create([
        'department_id' => $dept->id,
        'project_code' => 'CPX-2026-ASMB-777',
        'name' => 'Line Upgrading Project',
        'asset_code' => 'AST-999',
        'status' => 'ACTIVE',
    ]);

    $emp = Employee::factory()->create(['department_id' => $dept->id, 'npk' => 'EXP-001', 'full_name' => 'Export Tester']);

    $sub = makeSubmission($dept->id, null, $admin->id, '2026-09-08', 'OT-SUB-EXP-01');

    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'capex_project_id' => $project->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 4.5,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 225000.0,
        'status' => 'APPROVED',
    ]);

    // Test XLSX Export
    $xlsxResponse = $this->actingAs($admin)
        ->get(route('admin.capex-projects.export-attribution', ['format' => 'xlsx']));

    $xlsxResponse->assertOk();
    $xlsxResponse->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($xlsxResponse->headers->get('Content-Disposition'))->toContain('Laporan-Atribusi-CapEx-');
    expect($xlsxResponse->headers->get('Content-Disposition'))->toContain('.xlsx');

    // Test CSV Export
    $csvResponse = $this->actingAs($admin)
        ->get(route('admin.capex-projects.export-attribution', ['format' => 'csv']));

    $csvResponse->assertOk();
    $csvResponse->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    // Consume streamed content so stream callback executes and writes log
    $content = $csvResponse->streamedContent();
    expect($content)->toContain('CPX-2026-ASMB-777');
    expect($content)->toContain('Subtotal Proyek: CPX-2026-ASMB-777');
    expect($content)->toContain('Grand Total Seluruh Proyek');

    // Test export audit log creation
    $exportLogs = ExportLog::where('actor_user_id', $admin->id)
        ->where('resource_type', 'capex_labor_attribution')
        ->get();

    expect($exportLogs->count())->toBeGreaterThanOrEqual(2);
});
