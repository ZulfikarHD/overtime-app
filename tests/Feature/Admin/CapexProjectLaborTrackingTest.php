<?php

use App\Models\CapexProject;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeItemAudit;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use App\Notifications\CapexBurnAlertNotification;
use App\Services\CapExAccountingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-09-09 10:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('guest is redirected to login when accessing project cockpit or updating progress', function () {
    $project = CapexProject::factory()->create();

    $this->get(route('admin.capex-projects.show', $project))
        ->assertRedirect(route('login'));

    $this->patch(route('admin.capex-projects.progress.update', $project), [
        'physical_progress_pct' => 50.0,
    ])->assertRedirect(route('login'));
});

test('unauthorized roles are forbidden from accessing cockpit or updating progress', function () {
    $project = CapexProject::factory()->create();
    $teamLeader = User::factory()->teamLeader()->create();
    $operator = User::factory()->user()->create();

    $this->actingAs($teamLeader)
        ->get(route('admin.capex-projects.show', $project))
        ->assertForbidden();

    $this->actingAs($operator)
        ->get(route('admin.capex-projects.show', $project))
        ->assertForbidden();

    $this->actingAs($teamLeader)
        ->patch(route('admin.capex-projects.progress.update', $project), ['physical_progress_pct' => 50.0])
        ->assertForbidden();

    $this->actingAs($operator)
        ->patch(route('admin.capex-projects.progress.update', $project), ['physical_progress_pct' => 50.0])
        ->assertForbidden();
});

test('manager cannot view or update progress of another department project', function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();

    $managerA = User::factory()->manager($deptA->id)->create();
    $projectB = CapexProject::factory()->create(['department_id' => $deptB->id]);

    $this->actingAs($managerA)
        ->get(route('admin.capex-projects.show', $projectB))
        ->assertForbidden();

    $this->actingAs($managerA)
        ->patch(route('admin.capex-projects.progress.update', $projectB), ['physical_progress_pct' => 60.0])
        ->assertForbidden();
});

test('admin and department manager can view project cockpit with labor burn metrics, team contribution, and timeline', function () {
    $dept = Department::factory()->create();
    $sec = Section::factory()->create(['department_id' => $dept->id]);
    $admin = User::factory()->admin()->create();
    $manager = User::factory()->manager($dept->id)->create();

    $emp = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'npk' => 'TECH-001',
        'full_name' => 'Budi Santoso',
    ]);

    $project = CapexProject::factory()->create([
        'department_id' => $dept->id,
        'project_code' => 'CPX-2026-BODY-001',
        'allocated_labor_hours' => 100.0,
        'allocated_labor_budget_idr' => 15000000.0,
        'physical_progress_pct' => 45.0,
        'start_date' => '2026-08-01',
        'target_end_date' => '2026-10-31',
        'status' => 'ACTIVE',
    ]);

    OperationalCalendar::firstOrCreate(
        ['calendar_date' => '2026-08-15'],
        ['day_type' => 'HKN', 'is_holiday' => false]
    );

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-SUB-CPX-TEST-TRACK-01',
        'submission_date' => '2026-08-15',
        'operational_date' => '2026-08-15',
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
        'capex_project_id' => $project->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 40.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 60000,
        'total_cost_snapshot' => 2400000,
        'status' => 'APPROVED',
    ]);

    // Admin assertion
    $this->actingAs($admin)
        ->get(route('admin.capex-projects.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/CapexProjects/Show')
            ->has('project')
            ->has('metrics', fn (Assert $m) => $m
                ->where('allocated_hours', 100)
                ->where('consumed_hours', 40)
                ->where('remaining_hours', 60)
                ->where('burn_index_pct', 40)
                ->where('physical_progress_pct', 45)
                ->where('milestone_burn_ratio', 0.89)
                ->has('top_contributors.0', fn (Assert $c) => $c
                    ->where('npk', 'TECH-001')
                    ->where('name', 'Budi Santoso')
                    ->where('hours', 40)
                    ->etc()
                )
                ->has('weekly_timeline')
                ->etc()
            )
        );

    // Department Manager assertion
    $this->actingAs($manager)
        ->get(route('admin.capex-projects.show', $project))
        ->assertOk();
});

test('progress update validates bounds (rejects negative, non-numeric, and over 100)', function () {
    $admin = User::factory()->admin()->create();
    $project = CapexProject::factory()->create(['physical_progress_pct' => 10.0]);

    // Over 100
    $this->actingAs($admin)
        ->patch(route('admin.capex-projects.progress.update', $project), [
            'physical_progress_pct' => 105.0,
        ])
        ->assertSessionHasErrors(['physical_progress_pct']);

    // Negative
    $this->actingAs($admin)
        ->patch(route('admin.capex-projects.progress.update', $project), [
            'physical_progress_pct' => -5.0,
        ])
        ->assertSessionHasErrors(['physical_progress_pct']);

    // Non numeric
    $this->actingAs($admin)
        ->patch(route('admin.capex-projects.progress.update', $project), [
            'physical_progress_pct' => 'invalid-progress',
        ])
        ->assertSessionHasErrors(['physical_progress_pct']);

    expect((float) $project->fresh()->physical_progress_pct)->toBe(10.0);
});

test('progress update modifies physical_progress_pct and writes OvertimeItemAudit record', function () {
    $admin = User::factory()->admin()->create();
    $project = CapexProject::factory()->create([
        'physical_progress_pct' => 25.0,
        'project_code' => 'CPX-2026-AUDIT-001',
    ]);

    $response = $this->actingAs($admin)
        ->patch(route('admin.capex-projects.progress.update', $project), [
            'physical_progress_pct' => 75.5,
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('success');

    $fresh = $project->fresh();
    expect((float) $fresh->physical_progress_pct)->toBe(75.5);

    $this->assertDatabaseHas('overtime_item_audits', [
        'action' => 'PROGRESS_UPDATE',
        'actor_user_id' => $admin->id,
        'overtime_item_id' => null,
    ]);

    $audit = OvertimeItemAudit::query()
        ->where('action', 'PROGRESS_UPDATE')
        ->where('actor_user_id', $admin->id)
        ->latest('created_at')
        ->first();

    expect($audit)->not->toBeNull();
    expect((float) $audit->previous_state['physical_progress_pct'])->toBe(25.0);
    expect((float) $audit->new_state['physical_progress_pct'])->toBe(75.5);
    expect($audit->notes)->toContain('CPX-2026-AUDIT-001');
});

test('metrics calculation correctly sums consumed hours and cost snapshots only from approved overtime items', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create();
    $sec = Section::factory()->create(['department_id' => $dept->id]);
    $emp = Employee::factory()->create(['department_id' => $dept->id, 'section_id' => $sec->id]);

    OperationalCalendar::firstOrCreate(
        ['calendar_date' => '2026-09-01'],
        ['day_type' => 'HKN', 'is_holiday' => false]
    );
    OperationalCalendar::firstOrCreate(
        ['calendar_date' => '2026-09-02'],
        ['day_type' => 'HKN', 'is_holiday' => false]
    );

    $project = CapexProject::factory()->create([
        'department_id' => $dept->id,
        'allocated_labor_hours' => 200.0,
        'allocated_labor_budget_idr' => 20000000.0,
        'physical_progress_pct' => 50.0,
    ]);

    // Approved submission
    $subApproved = OvertimeSubmission::create([
        'submission_code' => 'OT-SUB-APP-01',
        'submission_date' => '2026-09-01',
        'operational_date' => '2026-09-01',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'submitted_by_user_id' => $admin->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $subApproved->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'capex_project_id' => $project->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 50.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000,
        'total_cost_snapshot' => 2500000,
        'status' => 'APPROVED',
    ]);

    // Rejected submission item (should be excluded)
    $subRejected = OvertimeSubmission::create([
        'submission_code' => 'OT-SUB-REJ-01',
        'submission_date' => '2026-09-02',
        'operational_date' => '2026-09-02',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'submitted_by_user_id' => $admin->id,
        'status' => 'REJECTED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $subRejected->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'capex_project_id' => $project->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 30.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000,
        'total_cost_snapshot' => 1500000,
        'status' => 'REJECTED',
    ]);

    $accountingService = app(CapExAccountingService::class);
    $metrics = $accountingService->getProjectLaborMetrics($project);

    expect($metrics['consumed_hours'])->toBe(50.0);
    expect($metrics['consumed_cost_idr'])->toBe(2500000.0);
    expect($metrics['burn_index_pct'])->toBe(25.0);
    expect($metrics['milestone_burn_ratio'])->toBe(0.5);
});

test('burn alert notification is dispatched when CapEx Burn Index > 80% and deduplicated per calendar month', function () {
    Notification::fake();

    $dept = Department::factory()->create();
    $admin = User::factory()->admin()->create();
    $manager = User::factory()->manager($dept->id)->create();

    $project = CapexProject::factory()->create([
        'department_id' => $dept->id,
        'allocated_labor_hours' => 100.0,
        'physical_progress_pct' => 40.0,
    ]);

    $accountingService = app(CapExAccountingService::class);

    // Below 80%: should not notify
    $notified = $accountingService->evaluateAndNotifyBurnAlert($project, 75.0);
    expect($notified)->toBeFalse();
    Notification::assertNothingSent();

    // Above 80%: should notify
    $notified = $accountingService->evaluateAndNotifyBurnAlert($project, 85.0);
    expect($notified)->toBeTrue();

    Notification::assertSentTo(
        [$admin, $manager],
        CapexBurnAlertNotification::class,
        function (CapexBurnAlertNotification $notification) use ($project) {
            return $notification->project->id === $project->id
                && $notification->burnIndexPct === 85.0;
        }
    );

    // Simulate database record written to notifications table
    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => CapexBurnAlertNotification::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $admin->id,
        'data' => json_encode(['project_id' => $project->id]),
        'read_at' => null,
        'created_at' => Carbon::now('Asia/Jakarta'),
        'updated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    // Second alert in same month: deduplicated
    $secondAttempt = $accountingService->evaluateAndNotifyBurnAlert($project, 92.0);
    expect($secondAttempt)->toBeFalse();
});

test('zero hours project returns safe zero metrics without division-by-zero errors', function () {
    $dept = Department::factory()->create();
    $project = CapexProject::factory()->create([
        'department_id' => $dept->id,
        'allocated_labor_hours' => 0.0,
        'allocated_labor_budget_idr' => 0.0,
        'physical_progress_pct' => 0.0,
    ]);

    $accountingService = app(CapExAccountingService::class);
    $metrics = $accountingService->getProjectLaborMetrics($project);

    expect($metrics['consumed_hours'])->toBe(0.0);
    expect($metrics['burn_index_pct'])->toBe(0.0);
    expect($metrics['milestone_burn_ratio'])->toBe(0.0);
    expect($metrics['top_contributors'])->toBe([]);
    expect($metrics['weekly_timeline'])->toBeArray();
});
