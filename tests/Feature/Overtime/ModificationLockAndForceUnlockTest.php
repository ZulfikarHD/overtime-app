<?php

use App\Jobs\RecalculateMonthlyBurnSnapshotJob;
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

function setupLockedSubmission(string $submissionStatus = 'APPROVED', string $itemStatus = 'APPROVED'): array
{
    $date = Carbon::now('Asia/Jakarta')->format('Y-m-d');
    if (! OperationalCalendar::whereDate('calendar_date', $date)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $date,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }

    $dept = Department::factory()->create(['default_hourly_rate' => 30000.00]);
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();
    $manager = User::factory()->manager($dept->id)->create();
    $admin = User::factory()->admin()->create();

    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'hourly_rate' => 35000.00,
    ]);

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-'.str_replace('-', '', $date).'-SEC-'.uniqid(),
        'submission_date' => $date,
        'operational_date' => $date,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => $submissionStatus,
        'total_hours_cached' => 3.0,
    ]);

    $item = OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 3.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 35000.00,
        'total_cost_snapshot' => 105000.00,
        'status' => $itemStatus,
        'lock_version' => 1,
        'reviewed_by_user_id' => $manager->id,
        'reviewed_at' => Carbon::now('Asia/Jakarta'),
    ]);

    return compact('dept', 'section', 'teamLeader', 'manager', 'admin', 'emp', 'submission', 'item');
}

test('team leader cannot edit, update, or delete submission containing approved items (HTTP 422)', function () {
    $data = setupLockedSubmission('APPROVED', 'APPROVED');
    $teamLeader = $data['teamLeader'];
    $submission = $data['submission'];
    $emp = $data['emp'];
    $dept = $data['dept'];
    $section = $data['section'];

    // 1. GET /edit should abort 422
    $this->actingAs($teamLeader)
        ->get(route('overtime.submissions.edit', $submission->id))
        ->assertStatus(422);

    // 2. PUT /update should abort 422
    $this->actingAs($teamLeader)
        ->put(route('overtime.submissions.update', $submission->id), [
            'operational_date' => $submission->operational_date->toDateString(),
            'department_id' => $dept->id,
            'section_id' => $section->id,
            'items' => [
                [
                    'employee_id' => $emp->id,
                    'hours_production' => 4.0,
                ],
            ],
        ])
        ->assertStatus(422);

    // 3. DELETE /destroy should abort 422
    $this->actingAs($teamLeader)
        ->delete(route('overtime.submissions.destroy', $submission->id))
        ->assertStatus(422);

    // Submission and item must still exist in database
    expect(OvertimeSubmission::find($submission->id))->not->toBeNull();
    expect(OvertimeItem::find($data['item']->id))->not->toBeNull();
});

test('partially approved submission is also locked against edit, update, and delete', function () {
    $data = setupLockedSubmission('PARTIALLY_APPROVED', 'APPROVED');
    $teamLeader = $data['teamLeader'];
    $submission = $data['submission'];

    $this->actingAs($teamLeader)
        ->get(route('overtime.submissions.edit', $submission->id))
        ->assertStatus(422);

    $this->actingAs($teamLeader)
        ->delete(route('overtime.submissions.destroy', $submission->id))
        ->assertStatus(422);
});

test('team leader can delete unapproved submitted submission', function () {
    Queue::fake();

    $date = Carbon::now('Asia/Jakarta')->format('Y-m-d');
    if (! OperationalCalendar::whereDate('calendar_date', $date)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $date,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }

    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();
    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create();

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-CLEAN-'.uniqid(),
        'submission_date' => $date,
        'operational_date' => $date,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 2.0,
    ]);

    $item = OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 2.0,
        'hourly_rate_snapshot' => 30000.00,
        'total_cost_snapshot' => 60000.00,
        'status' => 'PENDING',
        'lock_version' => 1,
    ]);

    $this->actingAs($teamLeader)
        ->delete(route('overtime.submissions.destroy', $submission->id))
        ->assertRedirect(route('overtime.submissions.index'));

    expect(OvertimeSubmission::find($submission->id))->toBeNull();
    expect(OvertimeItem::find($item->id))->toBeNull();
    Queue::assertPushed(RecalculateMonthlyBurnSnapshotJob::class);
});

test('non-admin users cannot force-unlock submissions', function () {
    $data = setupLockedSubmission('APPROVED', 'APPROVED');
    $submission = $data['submission'];
    $teamLeader = $data['teamLeader'];
    $manager = $data['manager'];

    // Team Leader forbidden
    $this->actingAs($teamLeader)
        ->patch(route('overtime.submissions.unlock', $submission->id), [
            'reason' => 'Unauthorized unlock attempt',
        ])
        ->assertForbidden();

    // Manager forbidden
    $this->actingAs($manager)
        ->patch(route('overtime.submissions.unlock', $submission->id), [
            'reason' => 'Manager unlock attempt',
        ])
        ->assertForbidden();
});

test('admin force-unlock requires a documented reason with minimum 5 characters', function () {
    $data = setupLockedSubmission('APPROVED', 'APPROVED');
    $submission = $data['submission'];
    $admin = $data['admin'];

    // Empty reason
    $this->actingAs($admin)
        ->patch(route('overtime.submissions.unlock', $submission->id), [
            'reason' => '',
        ])
        ->assertSessionHasErrors('reason');

    // Too short reason (< 5 characters)
    $this->actingAs($admin)
        ->patch(route('overtime.submissions.unlock', $submission->id), [
            'reason' => 'test',
        ])
        ->assertSessionHasErrors('reason');
});

test('admin cannot unlock submission that is not locked', function () {
    $date = Carbon::now('Asia/Jakarta')->format('Y-m-d');
    if (! OperationalCalendar::whereDate('calendar_date', $date)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $date,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }

    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $admin = User::factory()->admin()->create();

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-UNLOCKED-'.uniqid(),
        'submission_date' => $date,
        'operational_date' => $date,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $admin->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 2.0,
    ]);

    $this->actingAs($admin)
        ->patch(route('overtime.submissions.unlock', $submission->id), [
            'reason' => 'Attempt to unlock non-locked submission',
        ])
        ->assertStatus(422);
});

test('admin can force-unlock approved submission, reverting status to SUBMITTED and logging immutable audit', function () {
    Queue::fake();

    $data = setupLockedSubmission('APPROVED', 'APPROVED');
    $submission = $data['submission'];
    $item = $data['item'];
    $admin = $data['admin'];
    $teamLeader = $data['teamLeader'];
    $dept = $data['dept'];
    $section = $data['section'];

    $reason = 'Koreksi NPK operator yang salah catat atas memo HR No. 124/HR/IX/2026';

    $response = $this->actingAs($admin)
        ->patch(route('overtime.submissions.unlock', $submission->id), [
            'reason' => $reason,
        ]);

    $response->assertSessionHasNoErrors();

    // 1. Verify Submission status returned to SUBMITTED
    $freshSub = $submission->fresh();
    expect($freshSub->status)->toBe('SUBMITTED');

    // 2. Verify Item reverted to PENDING and reviewer info cleared
    $freshItem = $item->fresh();
    expect($freshItem->status)->toBe('PENDING')
        ->and($freshItem->reviewed_by_user_id)->toBeNull()
        ->and($freshItem->reviewed_at)->toBeNull()
        ->and($freshItem->rejection_reason)->toBeNull()
        ->and($freshItem->lock_version)->toBe(2);

    // 3. Verify Item Audit record written
    $itemAudit = OvertimeItemAudit::where('overtime_item_id', $item->id)
        ->where('action', 'ADMIN_UNLOCK')
        ->latest('id')
        ->first();

    expect($itemAudit)->not->toBeNull()
        ->and($itemAudit->actor_user_id)->toBe($admin->id)
        ->and($itemAudit->notes)->toBe($reason)
        ->and($itemAudit->previous_state['status'])->toBe('APPROVED')
        ->and($itemAudit->new_state['status'])->toBe('PENDING');

    // 4. Verify Parent Submission Audit record written
    $submissionAudit = OvertimeItemAudit::whereNull('overtime_item_id')
        ->where('action', 'ADMIN_UNLOCK')
        ->latest('id')
        ->first();

    expect($submissionAudit)->not->toBeNull()
        ->and($submissionAudit->actor_user_id)->toBe($admin->id)
        ->and($submissionAudit->notes)->toBe($reason)
        ->and($submissionAudit->previous_state['status'])->toBe('APPROVED')
        ->and($submissionAudit->new_state['status'])->toBe('SUBMITTED');

    // 5. Verify Monthly Burn Recalculation dispatched
    Queue::assertPushed(RecalculateMonthlyBurnSnapshotJob::class);

    // 6. Verify Team Leader can now access edit page and update
    $this->actingAs($teamLeader)
        ->get(route('overtime.submissions.edit', $submission->id))
        ->assertOk();

    $this->actingAs($teamLeader)
        ->put(route('overtime.submissions.update', $submission->id), [
            'operational_date' => $submission->operational_date->toDateString(),
            'department_id' => $dept->id,
            'section_id' => $section->id,
            'items' => [
                [
                    'employee_id' => $data['emp']->id,
                    'hours_production' => 4.5,
                ],
            ],
        ])
        ->assertRedirect(route('overtime.submissions.index'));

    $updatedItem = $submission->items()->where('employee_id', $data['emp']->id)->first();
    expect($updatedItem)->not->toBeNull()
        ->and($updatedItem->hours_production)->toBe('4.50');
});
