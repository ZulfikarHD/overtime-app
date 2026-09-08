<?php

use App\Actions\Overtime\ApproveOvertimeItemsAction;
use App\Exceptions\OptimisticLockException;
use App\Jobs\RecalculateMonthlyBurnSnapshotJob;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeItemAudit;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

function ensureApproveCalendar(string $date = '2026-09-08'): void
{
    $exists = OperationalCalendar::whereDate('calendar_date', $date)->exists();

    if (! $exists) {
        OperationalCalendar::create([
            'calendar_date' => $date,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }
}

function createApproveFixture(int $itemCount = 2): array
{
    ensureApproveCalendar('2026-09-08');

    $dept = Department::factory()->create([
        'code' => 'DEPT_AP_'.uniqid(),
        'name' => 'Approval Dept',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_AP_'.uniqid(),
        'name' => 'Section AP',
        'is_active' => true,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();
    $manager = User::factory()->manager($dept->id)->create();

    $otherDept = Department::factory()->create([
        'code' => 'DEPT_OTHER_'.uniqid(),
        'name' => 'Other Dept',
        'is_active' => true,
    ]);
    $otherManager = User::factory()->manager($otherDept->id)->create(); // different department
    $admin = User::factory()->admin()->create();

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-20260908-AP-'.uniqid(),
        'submission_date' => '2026-09-08',
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 6.0,
    ]);

    $items = [];
    for ($i = 1; $i <= $itemCount; $i++) {
        $employee = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
            'full_name' => "Worker {$i}",
            'npk' => "NPK-AP-{$i}-".fake()->unique()->numerify('####'),
            'hourly_rate' => 30000,
            'is_active' => true,
        ]);

        $items[] = OvertimeItem::create([
            'overtime_submission_id' => $submission->id,
            'employee_id' => $employee->id,
            'npk_snapshot' => $employee->npk,
            'hours_production' => 3.0,
            'hours_tpm' => 0.0,
            'hours_project' => 0.0,
            'hours_others' => 0.0,
            'hourly_rate_snapshot' => 30000,
            'total_cost_snapshot' => 3.0 * 30000,
            'status' => 'PENDING',
            'task_description' => "Shift job {$i}",
            'lock_version' => 1,
        ]);
    }

    return compact('dept', 'section', 'teamLeader', 'manager', 'otherManager', 'admin', 'submission', 'items');
}

test('action approves all items and updates submission status to APPROVED', function () {
    Queue::fake();

    $fixture = createApproveFixture(2);
    $action = app(ApproveOvertimeItemsAction::class);

    $decisions = [
        [
            'item_id' => $fixture['items'][0]->id,
            'action' => 'APPROVED',
            'lock_version' => 1,
        ],
        [
            'item_id' => $fixture['items'][1]->id,
            'action' => 'APPROVED',
            'lock_version' => 1,
        ],
    ];

    $updated = $action->execute($fixture['submission']->id, $decisions, $fixture['manager']->id);

    expect($updated->status)->toBe('APPROVED');

    $item1 = $fixture['items'][0]->fresh();
    $item2 = $fixture['items'][1]->fresh();

    expect($item1->status)->toBe('APPROVED')
        ->and($item1->reviewed_by_user_id)->toBe($fixture['manager']->id)
        ->and($item1->lock_version)->toBe(2);

    expect($item2->status)->toBe('APPROVED')
        ->and($item2->reviewed_by_user_id)->toBe($fixture['manager']->id)
        ->and($item2->lock_version)->toBe(2);

    // Audit logs written
    expect(OvertimeItemAudit::where('overtime_item_id', $item1->id)->count())->toBe(1)
        ->and(OvertimeItemAudit::where('overtime_item_id', $item2->id)->count())->toBe(1);

    Queue::assertPushed(RecalculateMonthlyBurnSnapshotJob::class);
});

test('action partially approves items and updates submission status to PARTIALLY_APPROVED', function () {
    Queue::fake();

    $fixture = createApproveFixture(2);
    $action = app(ApproveOvertimeItemsAction::class);

    $decisions = [
        [
            'item_id' => $fixture['items'][0]->id,
            'action' => 'APPROVED',
            'lock_version' => 1,
        ],
        [
            'item_id' => $fixture['items'][1]->id,
            'action' => 'REJECTED',
            'rejection_reason' => 'Target shift tercapai tanpa lembur.',
            'lock_version' => 1,
        ],
    ];

    $updated = $action->execute($fixture['submission']->id, $decisions, $fixture['manager']->id);

    expect($updated->status)->toBe('PARTIALLY_APPROVED');

    $item1 = $fixture['items'][0]->fresh();
    $item2 = $fixture['items'][1]->fresh();

    expect($item1->status)->toBe('APPROVED')
        ->and($item2->status)->toBe('REJECTED')
        ->and($item2->rejection_reason)->toBe('Target shift tercapai tanpa lembur.');

    $auditRejected = OvertimeItemAudit::where('overtime_item_id', $item2->id)->first();
    expect($auditRejected)->not->toBeNull()
        ->and($auditRejected->action)->toBe('REJECTED')
        ->and($auditRejected->notes)->toBe('Target shift tercapai tanpa lembur.');
});

test('action rejects all items and updates submission status to REJECTED', function () {
    Queue::fake();

    $fixture = createApproveFixture(2);
    $action = app(ApproveOvertimeItemsAction::class);

    $decisions = [
        [
            'item_id' => $fixture['items'][0]->id,
            'action' => 'REJECTED',
            'rejection_reason' => 'Target shift tercapai.',
            'lock_version' => 1,
        ],
        [
            'item_id' => $fixture['items'][1]->id,
            'action' => 'REJECTED',
            'rejection_reason' => 'Bukan jam lembur resmi.',
            'lock_version' => 1,
        ],
    ];

    $updated = $action->execute($fixture['submission']->id, $decisions, $fixture['manager']->id);

    expect($updated->status)->toBe('REJECTED');
});

test('action throws ValidationException if rejection reason is missing or less than 5 chars', function () {
    $fixture = createApproveFixture(1);
    $action = app(ApproveOvertimeItemsAction::class);

    $decisions = [
        [
            'item_id' => $fixture['items'][0]->id,
            'action' => 'REJECTED',
            'rejection_reason' => 'Bad', // only 3 chars
            'lock_version' => 1,
        ],
    ];

    expect(fn () => $action->execute($fixture['submission']->id, $decisions, $fixture['manager']->id))
        ->toThrow(ValidationException::class);
});

test('action throws OptimisticLockException if lock_version mismatches', function () {
    $fixture = createApproveFixture(1);
    $action = app(ApproveOvertimeItemsAction::class);

    // Simulate item was already updated by someone else, so lock_version is 2
    $fixture['items'][0]->update(['lock_version' => 2]);

    $decisions = [
        [
            'item_id' => $fixture['items'][0]->id,
            'action' => 'APPROVED',
            'lock_version' => 1, // Stale version
        ],
    ];

    expect(fn () => $action->execute($fixture['submission']->id, $decisions, $fixture['manager']->id))
        ->toThrow(OptimisticLockException::class);
});

test('manager can approve items via HTTP endpoint and receives 200 JSON', function () {
    Queue::fake();

    $fixture = createApproveFixture(2);

    $payload = [
        'decisions' => [
            [
                'item_id' => $fixture['items'][0]->id,
                'action' => 'APPROVED',
                'lock_version' => 1,
            ],
            [
                'item_id' => $fixture['items'][1]->id,
                'action' => 'REJECTED',
                'rejection_reason' => 'Kategori CapEx tidak valid.',
                'lock_version' => 1,
            ],
        ],
    ];

    $response = $this->actingAs($fixture['manager'])
        ->postJson(route('overtime.submissions.approve-items', $fixture['submission']), $payload);

    $response->assertOk()
        ->assertJson([
            'approved_count' => 1,
            'rejected_count' => 1,
        ])
        ->assertJsonPath('submission.status', 'PARTIALLY_APPROVED');
});

test('HTTP endpoint returns 409 conflict when lock version mismatch occurs', function () {
    $fixture = createApproveFixture(1);
    $fixture['items'][0]->update(['lock_version' => 5]);

    $payload = [
        'decisions' => [
            [
                'item_id' => $fixture['items'][0]->id,
                'action' => 'APPROVED',
                'lock_version' => 1,
            ],
        ],
    ];

    $response = $this->actingAs($fixture['manager'])
        ->postJson(route('overtime.submissions.approve-items', $fixture['submission']), $payload);

    $response->assertStatus(409)
        ->assertJson([
            'conflict' => true,
        ]);
});

test('manager from another department cannot approve submission', function () {
    $fixture = createApproveFixture(1);

    $payload = [
        'decisions' => [
            [
                'item_id' => $fixture['items'][0]->id,
                'action' => 'APPROVED',
                'lock_version' => 1,
            ],
        ],
    ];

    $response = $this->actingAs($fixture['otherManager'])
        ->postJson(route('overtime.submissions.approve-items', $fixture['submission']), $payload);

    $response->assertForbidden();
});

test('team leader cannot call approve items endpoint', function () {
    $fixture = createApproveFixture(1);

    $payload = [
        'decisions' => [
            [
                'item_id' => $fixture['items'][0]->id,
                'action' => 'APPROVED',
                'lock_version' => 1,
            ],
        ],
    ];

    $response = $this->actingAs($fixture['teamLeader'])
        ->postJson(route('overtime.submissions.approve-items', $fixture['submission']), $payload);

    $response->assertForbidden();
});
