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

function ensureBulkCalendar(string $date = '2026-09-08'): void
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

/**
 * @return array{
 *     dept: Department,
 *     section: Section,
 *     teamLeader: User,
 *     manager: User,
 *     admin: User,
 *     submissions: list<OvertimeSubmission>,
 *     items: list<OvertimeItem>
 * }
 */
function createBulkTestFixture(int $submissionCount = 3, int $itemsPerSubmission = 2): array
{
    ensureBulkCalendar('2026-09-08');

    $dept = Department::factory()->create([
        'code' => 'DEPT_BLK_'.uniqid(),
        'name' => 'Stamping Bulk Dept',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_BLK_'.uniqid(),
        'name' => 'Press Bulk Section',
        'is_active' => true,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();
    $manager = User::factory()->manager($dept->id)->create();
    $admin = User::factory()->admin()->create();

    $submissions = [];
    $items = [];

    for ($s = 1; $s <= $submissionCount; $s++) {
        $submission = OvertimeSubmission::create([
            'submission_code' => 'OT-20260908-BLK-'.uniqid(),
            'submission_date' => '2026-09-08',
            'operational_date' => '2026-09-08',
            'day_type' => 'HKN',
            'department_id' => $dept->id,
            'section_id' => $section->id,
            'submitted_by_user_id' => $teamLeader->id,
            'status' => 'SUBMITTED',
            'total_hours_cached' => $itemsPerSubmission * 3.0,
        ]);

        for ($i = 1; $i <= $itemsPerSubmission; $i++) {
            $employee = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
                'full_name' => "Worker S{$s}-I{$i}",
                'npk' => 'NPK-BLK-'.fake()->unique()->numerify('#####'),
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
                'task_description' => "Bulk task S{$s}-I{$i}",
                'lock_version' => 1,
            ]);
        }

        $submissions[] = $submission;
    }

    return compact('dept', 'section', 'teamLeader', 'manager', 'admin', 'submissions', 'items');
}

test('guest is redirected to login when calling bulk approvals endpoint', function () {
    $this->postJson(route('overtime.approvals.bulk'), [
        'submission_ids' => [1, 2],
        'action' => 'APPROVED',
    ])->assertUnauthorized();
});

test('team leader and operator cannot access bulk approvals endpoint', function () {
    $fixture = createBulkTestFixture(1, 1);

    $this->actingAs($fixture['teamLeader'])
        ->postJson(route('overtime.approvals.bulk'), [
            'submission_ids' => [$fixture['submissions'][0]->id],
            'action' => 'APPROVED',
        ])
        ->assertForbidden();

    $operator = User::factory()->user()->create();

    $this->actingAs($operator)
        ->postJson(route('overtime.approvals.bulk'), [
            'submission_ids' => [$fixture['submissions'][0]->id],
            'action' => 'APPROVED',
        ])
        ->assertForbidden();
});

test('manager can bulk approve multiple submissions in their department', function () {
    Queue::fake();

    $fixture = createBulkTestFixture(3, 2);
    $subIds = collect($fixture['submissions'])->pluck('id')->all();

    $response = $this->actingAs($fixture['manager'])
        ->postJson(route('overtime.approvals.bulk'), [
            'submission_ids' => $subIds,
            'action' => 'APPROVED',
        ]);

    $response->assertOk()
        ->assertJson([
            'processed_submissions_count' => 3,
            'skipped_submissions_count' => 0,
            'processed_items_count' => 6,
            'skipped_items_count' => 0,
        ]);

    // Verify all submissions updated to APPROVED
    foreach ($fixture['submissions'] as $sub) {
        expect($sub->fresh()->status)->toBe('APPROVED');
    }

    // Verify all items updated to APPROVED
    foreach ($fixture['items'] as $item) {
        $fresh = $item->fresh();
        expect($fresh->status)->toBe('APPROVED');
        expect($fresh->reviewed_by_user_id)->toBe($fixture['manager']->id);
        expect($fresh->reviewed_at)->not->toBeNull();
    }

    // Verify individual OvertimeItemAudit records created per item
    $auditCount = OvertimeItemAudit::whereIn('overtime_item_id', collect($fixture['items'])->pluck('id'))
        ->where('action', 'APPROVED')
        ->count();

    expect($auditCount)->toBe(6);

    // Verify RecalculateMonthlyBurnSnapshotJob dispatched
    Queue::assertPushed(RecalculateMonthlyBurnSnapshotJob::class, 3);
});

test('manager can bulk reject multiple submissions with mandatory rejection reason', function () {
    Queue::fake();

    $fixture = createBulkTestFixture(2, 2);
    $subIds = collect($fixture['submissions'])->pluck('id')->all();
    $reason = 'Target produksi shift telah tercapai';

    $response = $this->actingAs($fixture['manager'])
        ->postJson(route('overtime.approvals.bulk'), [
            'submission_ids' => $subIds,
            'action' => 'REJECTED',
            'rejection_reason' => $reason,
        ]);

    $response->assertOk()
        ->assertJson([
            'processed_submissions_count' => 2,
            'skipped_submissions_count' => 0,
            'processed_items_count' => 4,
            'skipped_items_count' => 0,
        ]);

    // Verify all submissions updated to REJECTED
    foreach ($fixture['submissions'] as $sub) {
        expect($sub->fresh()->status)->toBe('REJECTED');
    }

    // Verify all items updated to REJECTED with reason
    foreach ($fixture['items'] as $item) {
        $fresh = $item->fresh();
        expect($fresh->status)->toBe('REJECTED');
        expect($fresh->rejection_reason)->toBe($reason);
    }

    // Verify audits contain rejection notes
    $audits = OvertimeItemAudit::whereIn('overtime_item_id', collect($fixture['items'])->pluck('id'))->get();
    expect($audits)->toHaveCount(4);
    foreach ($audits as $audit) {
        expect($audit->action)->toBe('REJECTED');
        expect($audit->notes)->toBe($reason);
    }
});

test('bulk reject fails validation when rejection reason is missing or shorter than 5 characters', function () {
    $fixture = createBulkTestFixture(1, 1);
    $subId = $fixture['submissions'][0]->id;

    $this->actingAs($fixture['manager'])
        ->postJson(route('overtime.approvals.bulk'), [
            'submission_ids' => [$subId],
            'action' => 'REJECTED',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rejection_reason']);

    $this->actingAs($fixture['manager'])
        ->postJson(route('overtime.approvals.bulk'), [
            'submission_ids' => [$subId],
            'action' => 'REJECTED',
            'rejection_reason' => 'abc',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rejection_reason']);
});

test('bulk action enforces max 50 submissions limit', function () {
    $fixture = createBulkTestFixture(1, 1);

    // Array with 51 dummy IDs
    $ids = range(1, 51);

    $this->actingAs($fixture['manager'])
        ->postJson(route('overtime.approvals.bulk'), [
            'submission_ids' => $ids,
            'action' => 'APPROVED',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['submission_ids']);
});

test('manager cannot process submissions from other departments and skips them gracefully', function () {
    $fixture1 = createBulkTestFixture(1, 1);

    // Another department submission
    $otherDept = Department::factory()->create([
        'code' => 'DEPT_OTHER_'.uniqid(),
        'name' => 'Other Dept',
        'is_active' => true,
    ]);
    $otherSec = Section::factory()->create([
        'department_id' => $otherDept->id,
        'code' => 'SEC_OTHER_'.uniqid(),
        'name' => 'Other Sec',
        'is_active' => true,
    ]);
    $otherSub = OvertimeSubmission::create([
        'submission_code' => 'OT-OTHER-001',
        'submission_date' => '2026-09-08',
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $otherDept->id,
        'section_id' => $otherSec->id,
        'submitted_by_user_id' => $fixture1['teamLeader']->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 3.0,
    ]);
    $emp = Employee::factory()->forDepartmentAndSection($otherDept, $otherSec)->create();
    OvertimeItem::create([
        'overtime_submission_id' => $otherSub->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 3.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 30000,
        'total_cost_snapshot' => 90000,
        'status' => 'PENDING',
        'task_description' => 'Other task',
        'lock_version' => 1,
    ]);

    $response = $this->actingAs($fixture1['manager'])
        ->postJson(route('overtime.approvals.bulk'), [
            'submission_ids' => [$fixture1['submissions'][0]->id, $otherSub->id],
            'action' => 'APPROVED',
        ]);

    $response->assertOk()
        ->assertJson([
            'processed_submissions_count' => 1,
            'skipped_submissions_count' => 1,
            'processed_items_count' => 1,
            'skipped_items_count' => 1,
        ]);

    expect($fixture1['submissions'][0]->fresh()->status)->toBe('APPROVED');
    expect($otherSub->fresh()->status)->toBe('SUBMITTED');
});

test('admin can bulk approve submissions across multiple departments', function () {
    $fixture1 = createBulkTestFixture(1, 1);
    $fixture2 = createBulkTestFixture(1, 1);

    $response = $this->actingAs($fixture1['admin'])
        ->postJson(route('overtime.approvals.bulk'), [
            'submission_ids' => [$fixture1['submissions'][0]->id, $fixture2['submissions'][0]->id],
            'action' => 'APPROVED',
        ]);

    $response->assertOk()
        ->assertJson([
            'processed_submissions_count' => 2,
            'skipped_submissions_count' => 0,
            'processed_items_count' => 2,
            'skipped_items_count' => 0,
        ]);

    expect($fixture1['submissions'][0]->fresh()->status)->toBe('APPROVED');
    expect($fixture2['submissions'][0]->fresh()->status)->toBe('APPROVED');
});

test('partial failure: when one submission throws an exception, it rolls back and remaining submissions succeed', function () {
    $fixture = createBulkTestFixture(2, 1);
    $sub1 = $fixture['submissions'][0];
    $sub2 = $fixture['submissions'][1];

    $realAction = app(ApproveOvertimeItemsAction::class);
    $mock = Mockery::mock(ApproveOvertimeItemsAction::class);
    $mock->shouldReceive('execute')
        ->with($sub1->id, Mockery::any(), $fixture['manager']->id)
        ->andReturnUsing(fn ($id, $decisions, $userId) => $realAction->execute($id, $decisions, $userId));

    $mock->shouldReceive('execute')
        ->with($sub2->id, Mockery::any(), $fixture['manager']->id)
        ->andThrow(new OptimisticLockException('Data telah diubah oleh reviewer lain.'));

    $this->app->instance(ApproveOvertimeItemsAction::class, $mock);

    $response = $this->actingAs($fixture['manager'])
        ->postJson(route('overtime.approvals.bulk'), [
            'submission_ids' => [$sub1->id, $sub2->id],
            'action' => 'APPROVED',
        ]);

    $response->assertOk()
        ->assertJson([
            'processed_submissions_count' => 1,
            'skipped_submissions_count' => 1,
            'processed_items_count' => 1,
            'skipped_items_count' => 1,
            'skipped' => [
                [
                    'id' => $sub2->id,
                    'code' => $sub2->submission_code,
                    'reason' => 'Data telah diubah oleh reviewer lain.',
                ],
            ],
        ]);

    expect($sub1->fresh()->status)->toBe('APPROVED');
    expect($sub2->fresh()->status)->toBe('SUBMITTED');
});
