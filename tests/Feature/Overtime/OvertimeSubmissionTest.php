<?php

use App\Jobs\RunAnomalyDetectionJob;
use App\Models\CapexProject;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

function createTestSubmission(array $attributes = []): OvertimeSubmission
{
    $date = Carbon::parse($attributes['operational_date'] ?? '2026-09-08')->format('Y-m-d');
    $calendar = OperationalCalendar::whereDate('calendar_date', $date)->first();
    if (! $calendar) {
        OperationalCalendar::create([
            'calendar_date' => $date,
            'day_type' => $attributes['day_type'] ?? 'HKN',
            'is_holiday' => false,
        ]);
    }

    return OvertimeSubmission::create(array_merge([
        'submission_code' => 'OT-'.str_replace('-', '', (string) $date).'-TEST-'.uniqid(),
        'submission_date' => $date,
        'operational_date' => $date,
        'day_type' => 'HKN',
        'status' => 'SUBMITTED',
        'total_hours_cached' => 0.0,
    ], $attributes));
}

test('guest is redirected to login when accessing overtime submission routes', function () {
    $this->get(route('overtime.submissions.create'))->assertRedirect(route('login'));
    $this->post(route('overtime.submissions.store'), [])->assertRedirect(route('login'));
    $this->get(route('overtime.submissions.index'))->assertRedirect(route('login'));
});

test('team leader can view overtime create page with section roster and metadata', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_TEST_STP',
        'name' => 'Stamping Department',
        'default_hourly_rate' => 35000.00,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_TEST_PRESS',
        'name' => 'Press Section',
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();

    $emp1 = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'full_name' => 'Budi Santoso',
        'hourly_rate' => 38000.00,
        'is_active' => true,
    ]);

    $emp2 = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'full_name' => 'Agus Pratama',
        'hourly_rate' => null,
        'is_active' => true,
    ]);

    // Inactive employee should not appear in roster
    Employee::factory()->forDepartmentAndSection($dept, $section)->inactive()->create([
        'full_name' => 'Inactive Worker',
    ]);

    $this->actingAs($teamLeader)
        ->get(route('overtime.submissions.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('overtime/Create')
            ->has('departments')
            ->has('sections')
            ->where('selected_department_id', $dept->id)
            ->where('selected_section_id', $section->id)
            ->has('initial_roster', 2)
            ->where('initial_roster.0.id', $emp2->id) // Alphabetical by full_name: Agus before Budi
            ->has('burn_indicator')
        );
});

test('team leader can submit valid overtime batch with atomic transaction and financial snapshot', function () {
    Queue::fake();

    $dept = Department::factory()->create([
        'code' => 'DEPT_STP',
        'default_hourly_rate' => 30000.00,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_STP_01',
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();

    $emp1 = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'npk' => 'EMP-10001',
        'hourly_rate' => 40000.00,
    ]);

    $emp2 = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'npk' => 'EMP-10002',
        'hourly_rate' => null, // Will fall back to dept default 30000.00
    ]);

    $capex = CapexProject::create([
        'project_code' => 'CIP-2026-STP-01',
        'name' => 'Line Automation Stamping Phase 2',
        'department_id' => $dept->id,
        'allocated_labor_hours' => 100.00,
        'allocated_labor_budget_idr' => 5000000.00,
        'physical_progress_pct' => 0.00,
        'status' => 'ACTIVE',
        'start_date' => '2026-01-01',
        'target_end_date' => '2026-12-31',
    ]);

    $payload = [
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submission_notes' => 'Shift 2 Overtime Batch',
        'items' => [
            [
                'employee_id' => $emp1->id,
                'hours_production' => 2.5,
                'hours_tpm' => 1.0,
                'hours_project' => 1.5,
                'hours_others' => 0.0,
                'capex_project_id' => $capex->id,
                'rca_category' => 'MACHINE_BREAKDOWN',
                'rca_notes' => 'Line stoppage on station 4',
                'task_description' => 'Tooling setup',
            ],
            [
                'employee_id' => $emp2->id,
                'hours_production' => 3.0,
                'hours_tpm' => 0.0,
                'hours_project' => 0.0,
                'hours_others' => 0.5,
                'capex_project_id' => null,
                'rca_category' => null,
                'rca_notes' => null,
                'task_description' => 'Regular production overtime',
            ],
        ],
    ];

    $response = $this->actingAs($teamLeader)->post(route('overtime.submissions.store'), $payload);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('last_submission');

    // 1. Verify Submission Header
    $submission = OvertimeSubmission::where('section_id', $section->id)->first();
    expect($submission)->not->toBeNull();
    expect($submission->status)->toBe('SUBMITTED');
    expect($submission->day_type)->toBe('HKN');
    expect((float) $submission->total_hours_cached)->toBe(8.5); // (2.5+1+1.5) + (3+0.5) = 5.0 + 3.5 = 8.5
    expect($submission->submission_code)->toStartWith('OT-20260908-SECSTP01-');

    // 2. Verify Non-Blocking SPKL Container (BR-05)
    expect($submission->spklDocument)->not->toBeNull();
    expect($submission->spklDocument->status)->toBe('PENDING');
    expect($submission->spklDocument->due_date)->not->toBeNull();

    // 3. Verify Child Items & Immutable Financial Cost Snapshot (Story E03-02)
    expect($submission->items)->toHaveCount(2);

    $item1 = $submission->items()->where('employee_id', $emp1->id)->first();
    expect($item1->npk_snapshot)->toBe('EMP-10001');
    expect((float) $item1->hourly_rate_snapshot)->toBe(40000.00);
    // 5.0 hours * 40,000 = 200,000.00
    expect((float) $item1->total_cost_snapshot)->toBe(200000.00);
    expect($item1->capex_project_id)->toBe($capex->id);
    expect($item1->rca_category)->toBe('MACHINE_BREAKDOWN');

    $item2 = $submission->items()->where('employee_id', $emp2->id)->first();
    expect($item2->npk_snapshot)->toBe('EMP-10002');
    expect((float) $item2->hourly_rate_snapshot)->toBe(30000.00); // Department default fallback
    // 3.5 hours * 30,000 = 105,000.00
    expect((float) $item2->total_cost_snapshot)->toBe(105000.00);
    expect($item2->capex_project_id)->toBeNull();

    // 4. Verify Anomaly Detection Jobs Dispatched
    Queue::assertPushed(RunAnomalyDetectionJob::class, 2);
});

test('financial snapshot does not change after subsequent employee hourly rate update', function () {
    $dept = Department::factory()->create(['default_hourly_rate' => 30000.00]);
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();

    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'hourly_rate' => 45000.00,
    ]);

    $payload = [
        'operational_date' => '2026-09-08',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'items' => [
            [
                'employee_id' => $emp->id,
                'hours_production' => 4.0,
                'hours_tpm' => 0.0,
                'hours_project' => 0.0,
                'hours_others' => 0.0,
            ],
        ],
    ];

    $this->actingAs($teamLeader)->post(route('overtime.submissions.store'), $payload);

    $item = OvertimeItem::where('employee_id', $emp->id)->first();
    expect((float) $item->hourly_rate_snapshot)->toBe(45000.00);
    expect((float) $item->total_cost_snapshot)->toBe(180000.00);

    // Later: Employee gets a raise to Rp 60.000 / hour
    $emp->update(['hourly_rate' => 60000.00]);

    // Refresh item from database — snapshot MUST remain unchanged!
    $item->refresh();
    expect((float) $item->hourly_rate_snapshot)->toBe(45000.00);
    expect((float) $item->total_cost_snapshot)->toBe(180000.00);
});

test('overtime item hourly_rate_snapshot is strictly immutable against direct update', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();
    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create(['hourly_rate' => 35000.00]);

    $this->actingAs($teamLeader)->post(route('overtime.submissions.store'), [
        'operational_date' => '2026-09-08',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'items' => [
            ['employee_id' => $emp->id, 'hours_production' => 2.0],
        ],
    ]);

    $item = OvertimeItem::where('employee_id', $emp->id)->first();

    expect(fn () => $item->update(['hourly_rate_snapshot' => 99999.00]))
        ->toThrow(RuntimeException::class, 'immutable');
});

test('atomic rollback occurs if any employee row in the batch violates min 0.5 hours rule (BR-01)', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();

    $emp1 = Employee::factory()->forDepartmentAndSection($dept, $section)->create();
    $emp2 = Employee::factory()->forDepartmentAndSection($dept, $section)->create();

    $payload = [
        'operational_date' => '2026-09-08',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'items' => [
            [
                'employee_id' => $emp1->id,
                'hours_production' => 3.0, // Valid
            ],
            [
                'employee_id' => $emp2->id,
                'hours_production' => 0.0, // Invalid: total hours = 0 < 0.5
                'hours_tpm' => 0.0,
                'hours_project' => 0.0,
                'hours_others' => 0.0,
            ],
        ],
    ];

    $response = $this->actingAs($teamLeader)->post(route('overtime.submissions.store'), $payload);

    $response->assertSessionHasErrors(['items.1.hours_production']);

    // Ensure zero records persisted due to atomic transaction
    expect(OvertimeSubmission::count())->toBe(0);
    expect(OvertimeItem::count())->toBe(0);
});

test('capex project attribution is required when project hours are greater than 0 (BR-08)', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();

    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create();

    // 1. Missing capex_project_id
    $payloadMissing = [
        'operational_date' => '2026-09-08',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'items' => [
            [
                'employee_id' => $emp->id,
                'hours_project' => 2.0,
                'capex_project_id' => null,
            ],
        ],
    ];

    $response1 = $this->actingAs($teamLeader)->post(route('overtime.submissions.store'), $payloadMissing);
    $response1->assertSessionHasErrors(['items.0.capex_project_id']);
    expect(OvertimeSubmission::count())->toBe(0);

    // 2. Inactive CapEx project
    $inactiveCapex = CapexProject::create([
        'project_code' => 'CIP-INACTIVE',
        'name' => 'Inactive CapEx',
        'department_id' => $dept->id,
        'allocated_labor_hours' => 50.00,
        'allocated_labor_budget_idr' => 1000000.00,
        'physical_progress_pct' => 100.00,
        'status' => 'CLOSED',
        'start_date' => '2025-01-01',
        'target_end_date' => '2025-12-31',
    ]);

    $payloadInactive = [
        'operational_date' => '2026-09-08',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'items' => [
            [
                'employee_id' => $emp->id,
                'hours_project' => 2.0,
                'capex_project_id' => $inactiveCapex->id,
            ],
        ],
    ];

    $response2 = $this->actingAs($teamLeader)->post(route('overtime.submissions.store'), $payloadInactive);
    $response2->assertSessionHasErrors(['items.0.capex_project_id']);
    expect(OvertimeSubmission::count())->toBe(0);
});

test('team leader can override calendar day type and override is persisted', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();
    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create();

    // Default weekday 2026-09-08 would be HKN, override to HLR
    $payload = [
        'operational_date' => '2026-09-08',
        'day_type' => 'HLR',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'items' => [
            ['employee_id' => $emp->id, 'hours_production' => 3.0],
        ],
    ];

    $this->actingAs($teamLeader)->post(route('overtime.submissions.store'), $payload);

    $submission = OvertimeSubmission::first();
    expect($submission->day_type)->toBe('HLR');
});

test('user cannot submit for section they do not have access to', function () {
    $dept1 = Department::factory()->create();
    $sec1 = Section::factory()->create(['department_id' => $dept1->id]);

    $dept2 = Department::factory()->create();
    $sec2 = Section::factory()->create(['department_id' => $dept2->id]);

    $teamLeader = User::factory()->teamLeader($sec1->id, $dept1->id)->create();
    $empInSec2 = Employee::factory()->forDepartmentAndSection($dept2, $sec2)->create();

    $payload = [
        'operational_date' => '2026-09-08',
        'department_id' => $dept2->id,
        'section_id' => $sec2->id, // Belongs to dept2, TL is in sec1
        'items' => [
            ['employee_id' => $empInSec2->id, 'hours_production' => 2.0],
        ],
    ];

    $this->actingAs($teamLeader)->post(route('overtime.submissions.store'), $payload)->assertForbidden();
});

test('section roster endpoint returns active employees and burn indicator for authorized user', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();

    Employee::factory()->forDepartmentAndSection($dept, $section)->create(['is_active' => true]);
    Employee::factory()->forDepartmentAndSection($dept, $section)->inactive()->create();

    $this->actingAs($teamLeader)
        ->get(route('overtime.submissions.roster', $section->id))
        ->assertOk()
        ->assertJsonStructure([
            'employees' => [
                '*' => ['id', 'npk', 'full_name', 'job_position', 'hourly_rate'],
            ],
            'burn_indicator' => ['planned_hours', 'actual_hours', 'burn_pct', 'burn_zone'],
        ])
        ->assertJsonCount(1, 'employees');
});

test('history endpoint displays paginated overtime submissions with total_cost_cached', function () {
    $dept = Department::factory()->create(['default_hourly_rate' => 30000.00]);
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();
    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create(['hourly_rate' => 40000.00]);

    $this->actingAs($teamLeader)->post(route('overtime.submissions.store'), [
        'operational_date' => '2026-09-08',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'items' => [
            ['employee_id' => $emp->id, 'hours_production' => 2.5],
        ],
    ]);

    $this->actingAs($teamLeader)
        ->get(route('overtime.submissions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('overtime/Index')
            ->has('submissions.data', 1)
            ->where('submissions.data.0.total_cost_cached', fn ($val) => (float) $val === 100000.00)
            ->has('filters')
        );
});

test('history endpoint can filter by date range, status, section, and spkl status', function () {
    $dept = Department::factory()->create();
    $section1 = Section::factory()->create(['department_id' => $dept->id]);
    $section2 = Section::factory()->create(['department_id' => $dept->id]);

    $manager = User::factory()->manager($dept->id)->create();
    $emp1 = Employee::factory()->forDepartmentAndSection($dept, $section1)->create();
    $emp2 = Employee::factory()->forDepartmentAndSection($dept, $section2)->create();

    // Submission 1: Section 1, 2026-09-01, SUBMITTED
    $sub1 = createTestSubmission([
        'submission_code' => 'OT-20260901-SEC1-001',
        'submission_date' => '2026-09-01',
        'operational_date' => '2026-09-01',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section1->id,
        'submitted_by_user_id' => $manager->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 4.0,
    ]);
    $sub1->spklDocument()->create([
        'status' => 'PENDING',
        'due_date' => '2026-09-04',
    ]);

    // Submission 2: Section 2, 2026-09-05, APPROVED
    $sub2 = createTestSubmission([
        'submission_code' => 'OT-20260905-SEC2-001',
        'submission_date' => '2026-09-05',
        'operational_date' => '2026-09-05',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section2->id,
        'submitted_by_user_id' => $manager->id,
        'status' => 'APPROVED',
        'total_hours_cached' => 6.0,
    ]);
    $sub2->spklDocument()->create([
        'status' => 'ATTACHED',
        'due_date' => '2026-09-08',
    ]);

    // Filter by status APPROVED
    $this->actingAs($manager)
        ->get(route('overtime.submissions.index', ['status' => 'APPROVED']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('submissions.data', 1)
            ->where('submissions.data.0.id', $sub2->id)
        );

    // Filter by date range (2026-09-01 to 2026-09-03)
    $this->actingAs($manager)
        ->get(route('overtime.submissions.index', ['date_from' => '2026-09-01', 'date_to' => '2026-09-03']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('submissions.data', 1)
            ->where('submissions.data.0.id', $sub1->id)
        );

    // Filter by section_id
    $this->actingAs($manager)
        ->get(route('overtime.submissions.index', ['section_id' => $section2->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('submissions.data', 1)
            ->where('submissions.data.0.id', $sub2->id)
        );

    // Filter by SPKL status ATTACHED
    $this->actingAs($manager)
        ->get(route('overtime.submissions.index', ['spkl_status' => 'ATTACHED']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('submissions.data', 1)
            ->where('submissions.data.0.id', $sub2->id)
        );
});

test('submission show endpoint returns eagerly loaded json for modal inspection', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();
    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create(['hourly_rate' => 30000.00]);

    $submission = createTestSubmission([
        'submission_code' => 'OT-20260908-SEC-001',
        'submission_date' => '2026-09-08',
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 2.0,
    ]);

    $submission->items()->create([
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 2.0,
        'hourly_rate_snapshot' => 30000.00,
        'total_cost_snapshot' => 60000.00,
        'status' => 'PENDING',
        'lock_version' => 1,
    ]);

    $submission->spklDocument()->create([
        'status' => 'PENDING',
        'due_date' => '2026-09-11',
    ]);

    $response = $this->actingAs($teamLeader)
        ->getJson(route('overtime.submissions.show', $submission->id))
        ->assertOk();

    $response->assertJsonPath('submission.id', $submission->id);
    $response->assertJsonPath('submission.submission_code', 'OT-20260908-SEC-001');
    $response->assertJsonCount(1, 'submission.items');
    $response->assertJsonPath('submission.items.0.npk_snapshot', $emp->npk);
});

test('user cannot access show or edit for submission in an unauthorized section', function () {
    $dept1 = Department::factory()->create();
    $sec1 = Section::factory()->create(['department_id' => $dept1->id]);

    $dept2 = Department::factory()->create();
    $sec2 = Section::factory()->create(['department_id' => $dept2->id]);

    $teamLeaderSec1 = User::factory()->teamLeader($sec1->id, $dept1->id)->create();

    $submissionSec2 = createTestSubmission([
        'submission_code' => 'OT-20260908-SEC2-001',
        'submission_date' => '2026-09-08',
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $dept2->id,
        'section_id' => $sec2->id,
        'submitted_by_user_id' => User::factory()->create()->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 2.0,
    ]);

    $this->actingAs($teamLeaderSec1)
        ->get(route('overtime.submissions.show', $submissionSec2->id))
        ->assertForbidden();

    $this->actingAs($teamLeaderSec1)
        ->get(route('overtime.submissions.edit', $submissionSec2->id))
        ->assertForbidden();
});

test('team leader can view edit page for draft or submitted overtime submission', function () {
    $dept = Department::factory()->create(['default_hourly_rate' => 30000.00]);
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();
    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create();

    $submission = createTestSubmission([
        'submission_code' => 'OT-20260908-SEC-001',
        'submission_date' => '2026-09-08',
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 1.5,
    ]);

    $submission->items()->create([
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 1.5,
        'hourly_rate_snapshot' => 30000.00,
        'total_cost_snapshot' => 45000.00,
        'status' => 'PENDING',
        'lock_version' => 1,
    ]);

    $this->actingAs($teamLeader)
        ->get(route('overtime.submissions.edit', $submission->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('overtime/Create')
            ->has('editing_submission')
            ->where('editing_submission.id', $submission->id)
            ->where('editing_submission.submission_code', 'OT-20260908-SEC-001')
            ->has('editing_submission.items', 1)
        );
});

test('guarded edit: cannot access edit page or update submission when status is APPROVED or PARTIALLY_APPROVED', function () {
    $dept = Department::factory()->create(['default_hourly_rate' => 30000.00]);
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();
    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create();

    foreach (['APPROVED', 'PARTIALLY_APPROVED'] as $lockedStatus) {
        $submission = createTestSubmission([
            'submission_code' => 'OT-LOCKED-'.$lockedStatus,
            'submission_date' => '2026-09-08',
            'operational_date' => '2026-09-08',
            'day_type' => 'HKN',
            'department_id' => $dept->id,
            'section_id' => $section->id,
            'submitted_by_user_id' => $teamLeader->id,
            'status' => $lockedStatus,
            'total_hours_cached' => 2.0,
        ]);

        $submission->items()->create([
            'employee_id' => $emp->id,
            'npk_snapshot' => $emp->npk,
            'hours_production' => 2.0,
            'hourly_rate_snapshot' => 30000.00,
            'total_cost_snapshot' => 60000.00,
            'status' => 'APPROVED',
            'lock_version' => 1,
        ]);

        // Attempt GET edit
        $this->actingAs($teamLeader)
            ->get(route('overtime.submissions.edit', $submission->id))
            ->assertStatus(422);

        // Attempt PUT update
        $this->actingAs($teamLeader)
            ->put(route('overtime.submissions.update', $submission->id), [
                'operational_date' => '2026-09-08',
                'department_id' => $dept->id,
                'section_id' => $section->id,
                'items' => [
                    ['employee_id' => $emp->id, 'hours_production' => 3.0],
                ],
            ])
            ->assertStatus(422);
    }
});

test('team leader can update submitted overtime with fresh rate snapshots and atomic transaction', function () {
    Queue::fake();

    $dept = Department::factory()->create(['default_hourly_rate' => 30000.00]);
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();

    $emp1 = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'hourly_rate' => 40000.00,
    ]);
    $emp2 = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'hourly_rate' => 50000.00,
    ]);

    $submission = createTestSubmission([
        'submission_code' => 'OT-20260908-SEC-099',
        'submission_date' => '2026-09-08',
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 1.0,
    ]);

    $submission->items()->create([
        'employee_id' => $emp1->id,
        'npk_snapshot' => $emp1->npk,
        'hours_production' => 1.0,
        'hourly_rate_snapshot' => 40000.00,
        'total_cost_snapshot' => 40000.00,
        'status' => 'PENDING',
        'lock_version' => 1,
    ]);

    $submission->spklDocument()->create([
        'status' => 'PENDING',
        'due_date' => '2026-09-11',
    ]);

    // Update with emp2 having 2.5 hours
    $updatePayload = [
        'operational_date' => '2026-09-09',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submission_notes' => 'Catatan revisi pengajuan lembur',
        'items' => [
            [
                'employee_id' => $emp2->id,
                'hours_production' => 2.5,
                'hours_tpm' => 0.0,
                'hours_project' => 0.0,
                'hours_others' => 0.0,
                'task_description' => 'Overtime die trial tooling',
            ],
        ],
    ];

    $response = $this->actingAs($teamLeader)
        ->put(route('overtime.submissions.update', $submission->id), $updatePayload)
        ->assertRedirect(route('overtime.submissions.index'));

    $submission->refresh();
    expect($submission->operational_date->format('Y-m-d'))->toBe('2026-09-09');
    expect($submission->submission_notes)->toBe('Catatan revisi pengajuan lembur');
    expect((float) $submission->total_hours_cached)->toBe(2.5);

    // Old item removed, new item created with emp2 snapshot
    expect($submission->items()->count())->toBe(1);
    $item = $submission->items()->first();
    expect($item->employee_id)->toBe($emp2->id);
    expect($item->npk_snapshot)->toBe($emp2->npk);
    expect((float) $item->hourly_rate_snapshot)->toBe(50000.00);
    expect((float) $item->total_cost_snapshot)->toBe(125000.00); // 2.5 * 50000

    Queue::assertPushed(RunAnomalyDetectionJob::class);
});
