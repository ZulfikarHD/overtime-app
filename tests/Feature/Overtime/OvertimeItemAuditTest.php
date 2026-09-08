<?php

use App\Actions\Overtime\ApproveOvertimeItemsAction;
use App\Actions\Overtime\SubmitOvertimeAction;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeItemAudit;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;

function ensureAuditCalendar(string $date = '2026-09-08'): void
{
    if (! OperationalCalendar::whereDate('calendar_date', $date)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $date,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }
}

function createAuditFixture(): array
{
    ensureAuditCalendar('2026-09-08');

    $dept = Department::factory()->create([
        'code' => 'DEPT_AUDIT_'.uniqid(),
        'name' => 'Stamping Audit Dept',
        'default_hourly_rate' => 35000,
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_AUDIT_'.uniqid(),
        'name' => 'Press Section Audit',
        'is_active' => true,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create([
        'name' => 'Agus Team Leader',
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Budi Manager',
    ]);

    $otherDept = Department::factory()->create([
        'code' => 'DEPT_OTHER_'.uniqid(),
        'name' => 'Welding Dept',
        'is_active' => true,
    ]);

    $otherManager = User::factory()->manager($otherDept->id)->create([
        'name' => 'Siti Other Manager',
    ]);

    $admin = User::factory()->admin()->create([
        'name' => 'Plant Admin',
    ]);

    $emp1 = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'full_name' => 'Dedi Operator',
        'npk' => 'EMP-AUD-1001',
        'hourly_rate' => 35000,
        'is_active' => true,
    ]);

    $emp2 = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'full_name' => 'Eko Toolmaker',
        'npk' => 'EMP-AUD-1002',
        'hourly_rate' => 40000,
        'is_active' => true,
    ]);

    return compact('dept', 'section', 'teamLeader', 'manager', 'otherDept', 'otherManager', 'admin', 'emp1', 'emp2');
}

test('submitting overtime creates synchronous submitted audit records for items', function () {
    Queue::fake();
    $fixture = createAuditFixture();

    /** @var SubmitOvertimeAction $submitAction */
    $submitAction = app(SubmitOvertimeAction::class);

    $submission = $submitAction->execute([
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $fixture['dept']->id,
        'section_id' => $fixture['section']->id,
        'submission_notes' => 'Shift 1 Production',
        'items' => [
            [
                'employee_id' => $fixture['emp1']->id,
                'hours_production' => 3.5,
                'task_description' => 'Press maintenance and die change',
            ],
            [
                'employee_id' => $fixture['emp2']->id,
                'hours_production' => 2.0,
                'task_description' => 'Quality inspection shift handoff',
            ],
        ],
    ], $fixture['teamLeader']->id);

    $items = $submission->items;
    expect($items)->toHaveCount(2);

    foreach ($items as $item) {
        $audit = OvertimeItemAudit::where('overtime_item_id', $item->id)->first();
        expect($audit)->not->toBeNull()
            ->and($audit->action)->toBe('SUBMITTED')
            ->and($audit->actor_user_id)->toBe($fixture['teamLeader']->id)
            ->and($audit->previous_state)->toBeNull()
            ->and($audit->new_state)->toBeArray()
            ->and($audit->new_state['status'])->toBe('PENDING')
            ->and($audit->new_state['npk_snapshot'])->toBe($item->npk_snapshot)
            ->and($audit->notes)->toContain('Pengajuan lembur diserahkan');
    }
});

test('approving and rejecting items creates audit records with before and after states', function () {
    Queue::fake();
    $fixture = createAuditFixture();

    /** @var SubmitOvertimeAction $submitAction */
    $submitAction = app(SubmitOvertimeAction::class);

    $submission = $submitAction->execute([
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $fixture['dept']->id,
        'section_id' => $fixture['section']->id,
        'items' => [
            ['employee_id' => $fixture['emp1']->id, 'hours_production' => 3.0],
            ['employee_id' => $fixture['emp2']->id, 'hours_production' => 2.5],
        ],
    ], $fixture['teamLeader']->id);

    $item1 = $submission->items[0];
    $item2 = $submission->items[1];

    /** @var ApproveOvertimeItemsAction $approveAction */
    $approveAction = app(ApproveOvertimeItemsAction::class);

    $approveAction->execute($submission->id, [
        [
            'item_id' => $item1->id,
            'action' => 'APPROVED',
            'lock_version' => 1,
            'notes' => 'Disetujui sesuai target shift.',
        ],
        [
            'item_id' => $item2->id,
            'action' => 'REJECTED',
            'lock_version' => 1,
            'rejection_reason' => 'Jam lembur melebihi alokasi harian shift.',
        ],
    ], $fixture['manager']->id);

    // Item 1 Audits: SUBMITTED then APPROVED
    $item1Audits = OvertimeItemAudit::where('overtime_item_id', $item1->id)->orderBy('id')->get();
    expect($item1Audits)->toHaveCount(2)
        ->and($item1Audits[0]->action)->toBe('SUBMITTED')
        ->and($item1Audits[1]->action)->toBe('APPROVED')
        ->and($item1Audits[1]->actor_user_id)->toBe($fixture['manager']->id)
        ->and($item1Audits[1]->previous_state['status'])->toBe('PENDING')
        ->and($item1Audits[1]->new_state['status'])->toBe('APPROVED');

    // Item 2 Audits: SUBMITTED then REJECTED with mandatory reason in notes
    $item2Audits = OvertimeItemAudit::where('overtime_item_id', $item2->id)->orderBy('id')->get();
    expect($item2Audits)->toHaveCount(2)
        ->and($item2Audits[0]->action)->toBe('SUBMITTED')
        ->and($item2Audits[1]->action)->toBe('REJECTED')
        ->and($item2Audits[1]->actor_user_id)->toBe($fixture['manager']->id)
        ->and($item2Audits[1]->previous_state['status'])->toBe('PENDING')
        ->and($item2Audits[1]->new_state['status'])->toBe('REJECTED')
        ->and($item2Audits[1]->notes)->toBe('Jam lembur melebihi alokasi harian shift.');
});

test('overtime item audit is strictly immutable on update and delete', function () {
    $fixture = createAuditFixture();

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-IMMUTABLE-'.uniqid(),
        'submission_date' => '2026-09-08',
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $fixture['dept']->id,
        'section_id' => $fixture['section']->id,
        'submitted_by_user_id' => $fixture['teamLeader']->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 2.0,
    ]);

    $item = OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $fixture['emp1']->id,
        'npk_snapshot' => $fixture['emp1']->npk,
        'hours_production' => 2.0,
        'hourly_rate_snapshot' => 35000,
        'total_cost_snapshot' => 70000,
        'status' => 'PENDING',
        'lock_version' => 1,
    ]);

    $audit = OvertimeItemAudit::create([
        'overtime_item_id' => $item->id,
        'action' => 'SUBMITTED',
        'actor_user_id' => $fixture['teamLeader']->id,
        'previous_state' => null,
        'new_state' => $item->toArray(),
        'notes' => 'Initial submission',
        'created_at' => Carbon::now('Asia/Jakarta'),
    ]);

    // Mutation must throw RuntimeException
    expect(fn () => $audit->update(['notes' => 'Tampered notes']))
        ->toThrow(RuntimeException::class, 'Overtime item audit records are immutable and cannot be modified.');

    // Deletion must throw RuntimeException
    expect(fn () => $audit->delete())
        ->toThrow(RuntimeException::class, 'Overtime item audit records are immutable and cannot be deleted.');
});

test('manager can fetch audit trail for own department item', function () {
    $fixture = createAuditFixture();

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-API-'.uniqid(),
        'submission_date' => '2026-09-08',
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $fixture['dept']->id,
        'section_id' => $fixture['section']->id,
        'submitted_by_user_id' => $fixture['teamLeader']->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 3.0,
    ]);

    $item = OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $fixture['emp1']->id,
        'npk_snapshot' => $fixture['emp1']->npk,
        'hours_production' => 3.0,
        'hourly_rate_snapshot' => 35000,
        'total_cost_snapshot' => 105000,
        'status' => 'PENDING',
        'lock_version' => 1,
    ]);

    OvertimeItemAudit::create([
        'overtime_item_id' => $item->id,
        'action' => 'SUBMITTED',
        'actor_user_id' => $fixture['teamLeader']->id,
        'previous_state' => null,
        'new_state' => $item->toArray(),
        'notes' => 'Diajukan oleh Team Leader.',
        'ip_address' => '10.30.11.65',
        'created_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $response = $this->actingAs($fixture['manager'])
        ->getJson("/overtime/items/{$item->id}/audit")
        ->assertOk();

    $response->assertJsonPath('item.id', $item->id)
        ->assertJsonPath('item.npk_snapshot', $fixture['emp1']->npk)
        ->assertJsonPath('item.submission_code', $submission->submission_code)
        ->assertJsonCount(1, 'audits')
        ->assertJsonPath('audits.0.action', 'SUBMITTED')
        ->assertJsonPath('audits.0.actor.name', $fixture['teamLeader']->name)
        ->assertJsonPath('audits.0.ip_address', '10.30.11.65');
});

test('manager cannot fetch audit trail for other department item', function () {
    $fixture = createAuditFixture();

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-OTHER-'.uniqid(),
        'submission_date' => '2026-09-08',
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $fixture['dept']->id,
        'section_id' => $fixture['section']->id,
        'submitted_by_user_id' => $fixture['teamLeader']->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 2.0,
    ]);

    $item = OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $fixture['emp1']->id,
        'npk_snapshot' => $fixture['emp1']->npk,
        'hours_production' => 2.0,
        'hourly_rate_snapshot' => 35000,
        'total_cost_snapshot' => 70000,
        'status' => 'PENDING',
        'lock_version' => 1,
    ]);

    // Manager from another department receives 403
    $this->actingAs($fixture['otherManager'])
        ->getJson("/overtime/items/{$item->id}/audit")
        ->assertForbidden();
});

test('admin can fetch audit trail for any department item', function () {
    $fixture = createAuditFixture();

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-ADMIN-'.uniqid(),
        'submission_date' => '2026-09-08',
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $fixture['dept']->id,
        'section_id' => $fixture['section']->id,
        'submitted_by_user_id' => $fixture['teamLeader']->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 1.5,
    ]);

    $item = OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $fixture['emp1']->id,
        'npk_snapshot' => $fixture['emp1']->npk,
        'hours_production' => 1.5,
        'hourly_rate_snapshot' => 35000,
        'total_cost_snapshot' => 52500,
        'status' => 'PENDING',
        'lock_version' => 1,
    ]);

    OvertimeItemAudit::create([
        'overtime_item_id' => $item->id,
        'action' => 'SUBMITTED',
        'actor_user_id' => $fixture['teamLeader']->id,
        'previous_state' => null,
        'new_state' => $item->toArray(),
        'created_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $this->actingAs($fixture['admin'])
        ->getJson("/overtime/items/{$item->id}/audit")
        ->assertOk()
        ->assertJsonPath('item.id', $item->id)
        ->assertJsonCount(1, 'audits');
});

test('team leader and unauthenticated users cannot access audit trail endpoint', function () {
    $fixture = createAuditFixture();

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-GUARD-'.uniqid(),
        'submission_date' => '2026-09-08',
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $fixture['dept']->id,
        'section_id' => $fixture['section']->id,
        'submitted_by_user_id' => $fixture['teamLeader']->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 1.0,
    ]);

    $item = OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $fixture['emp1']->id,
        'npk_snapshot' => $fixture['emp1']->npk,
        'hours_production' => 1.0,
        'hourly_rate_snapshot' => 35000,
        'total_cost_snapshot' => 35000,
        'status' => 'PENDING',
        'lock_version' => 1,
    ]);

    // Team Leader is blocked by role:admin,manager middleware
    $this->actingAs($fixture['teamLeader'])
        ->getJson("/overtime/items/{$item->id}/audit")
        ->assertForbidden();

    // Guest is blocked
    $this->getJson("/overtime/items/{$item->id}/audit")
        ->assertForbidden();
});
