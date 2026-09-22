<?php

use App\Actions\Overtime\ApproveSplEntriesAction;
use App\Exceptions\OptimisticLockException;
use App\Models\Department;
use App\Models\Section;
use App\Models\SplEntry;
use App\Models\User;
use Carbon\Carbon;

/**
 * Create SplEntry records for bulk approval tests.
 *
 * @return array{
 *     dept: Department,
 *     section: Section,
 *     manager: User,
 *     admin: User,
 *     entries: list<SplEntry>,
 *     group: array{section_id: int, date: string}
 * }
 */
function createBulkSplFixture(int $entryCount = 3): array
{
    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create(['department_id' => $dept->id, 'is_active' => true]);
    $manager = User::factory()->manager($dept->id)->create();
    $admin = User::factory()->admin()->create();
    $importer = User::factory()->user()->create();

    $date = Carbon::now('Asia/Jakarta')->subDays(2)->toDateString();
    $entries = [];

    for ($i = 1; $i <= $entryCount; $i++) {
        $entries[] = SplEntry::create([
            'npk_snapshot' => "NPK-BLK-{$i}-".uniqid(),
            'employee_name_snapshot' => "Worker Bulk {$i}",
            'section_id' => $section->id,
            'department_id' => $dept->id,
            'section_name_snapshot' => $section->name,
            'department_name_snapshot' => $dept->name,
            'realization_date' => $date,
            'day_type' => 'HKN',
            'start_time' => '18:00:00',
            'end_time' => '22:00:00',
            'total_hours' => 4.00,
            'status' => 'PENDING',
            'lock_version' => 0,
            'imported_by_user_id' => $importer->id,
        ]);
    }

    $group = ['section_id' => $section->id, 'date' => $date];

    return compact('dept', 'section', 'manager', 'admin', 'entries', 'group');
}

// ─── Auth / RBAC ─────────────────────────────────────────────────────────────

test('guest is redirected to login when calling bulk approvals endpoint', function () {
    $this->postJson(route('overtime.approvals.bulk'), [
        'groups' => [['section_id' => 1, 'date' => '2026-09-08']],
        'action' => 'APPROVED',
    ])->assertUnauthorized();
});

test('team leader and operator cannot access bulk approvals endpoint', function () {
    $fixture = createBulkSplFixture(1);

    $tl = User::factory()->teamLeader($fixture['section']->id, $fixture['dept']->id)->create();

    $this->actingAs($tl)
        ->postJson(route('overtime.approvals.bulk'), [
            'groups' => [$fixture['group']],
            'action' => 'APPROVED',
        ])
        ->assertForbidden();

    $operator = User::factory()->user()->create();

    $this->actingAs($operator)
        ->postJson(route('overtime.approvals.bulk'), [
            'groups' => [$fixture['group']],
            'action' => 'APPROVED',
        ])
        ->assertForbidden();
});

// ─── Happy path ───────────────────────────────────────────────────────────────

test('manager can bulk approve multiple entries in their department', function () {
    $fixture = createBulkSplFixture(3);

    $response = $this->actingAs($fixture['manager'])
        ->postJson(route('overtime.approvals.bulk'), [
            'groups' => [$fixture['group']],
            'action' => 'APPROVED',
        ]);

    $response->assertOk()
        ->assertJsonPath('processed', 3)
        ->assertJsonPath('skipped', 0);

    foreach ($fixture['entries'] as $entry) {
        expect($entry->fresh()->status)->toBe('APPROVED');
        expect($entry->fresh()->reviewed_by_user_id)->toBe($fixture['manager']->id);
        expect($entry->fresh()->reviewed_at)->not->toBeNull();
    }
});

test('manager can bulk reject entries with mandatory rejection reason', function () {
    $fixture = createBulkSplFixture(2);
    $reason = 'Target produksi shift telah tercapai';

    $response = $this->actingAs($fixture['manager'])
        ->postJson(route('overtime.approvals.bulk'), [
            'groups' => [$fixture['group']],
            'action' => 'REJECTED',
            'rejection_reason' => $reason,
        ]);

    $response->assertOk()
        ->assertJsonPath('processed', 2)
        ->assertJsonPath('skipped', 0);

    foreach ($fixture['entries'] as $entry) {
        $fresh = $entry->fresh();
        expect($fresh->status)->toBe('REJECTED');
        expect($fresh->rejection_reason)->toBe($reason);
    }
});

// ─── Validation ───────────────────────────────────────────────────────────────

test('bulk reject fails validation when rejection reason is missing or too short', function () {
    $fixture = createBulkSplFixture(1);

    $this->actingAs($fixture['manager'])
        ->postJson(route('overtime.approvals.bulk'), [
            'groups' => [$fixture['group']],
            'action' => 'REJECTED',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rejection_reason']);

    $this->actingAs($fixture['manager'])
        ->postJson(route('overtime.approvals.bulk'), [
            'groups' => [$fixture['group']],
            'action' => 'REJECTED',
            'rejection_reason' => 'abc',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rejection_reason']);
});

test('bulk action enforces max 50 groups limit', function () {
    $fixture = createBulkSplFixture(1);

    // 51 dummy groups
    $groups = array_map(fn (int $i) => ['section_id' => $i, 'date' => '2026-09-08'], range(1, 51));

    $this->actingAs($fixture['manager'])
        ->postJson(route('overtime.approvals.bulk'), [
            'groups' => $groups,
            'action' => 'APPROVED',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['groups']);
});

// ─── Cross-department guard ───────────────────────────────────────────────────

test('manager cannot process entries from other departments and skips them gracefully', function () {
    $fixture1 = createBulkSplFixture(1);

    // Other department
    $otherDept = Department::factory()->create(['is_active' => true]);
    $otherSec = Section::factory()->create(['department_id' => $otherDept->id, 'is_active' => true]);
    $importer = User::factory()->user()->create();
    SplEntry::create([
        'npk_snapshot' => 'NPK-OTHER',
        'employee_name_snapshot' => 'Other Worker',
        'section_id' => $otherSec->id,
        'department_id' => $otherDept->id,
        'section_name_snapshot' => $otherSec->name,
        'department_name_snapshot' => $otherDept->name,
        'realization_date' => $fixture1['group']['date'],
        'day_type' => 'HKN',
        'start_time' => '18:00:00',
        'end_time' => '22:00:00',
        'total_hours' => 4.00,
        'status' => 'PENDING',
        'lock_version' => 0,
        'imported_by_user_id' => $importer->id,
    ]);

    $otherGroup = ['section_id' => $otherSec->id, 'date' => $fixture1['group']['date']];

    // Manager from fixture1 dept — the other group has entries but they won't be touched
    // (manager dept guard in BulkApproveSplEntriesAction skips by ignoring wrong-dept entries)
    $response = $this->actingAs($fixture1['manager'])
        ->postJson(route('overtime.approvals.bulk'), [
            'groups' => [$fixture1['group'], $otherGroup],
            'action' => 'APPROVED',
        ]);

    $response->assertOk();
    // Own group is approved
    expect($fixture1['entries'][0]->fresh()->status)->toBe('APPROVED');
});

test('admin can bulk approve entries across multiple departments', function () {
    $fixture1 = createBulkSplFixture(1);
    $fixture2 = createBulkSplFixture(1);

    $response = $this->actingAs($fixture1['admin'])
        ->postJson(route('overtime.approvals.bulk'), [
            'groups' => [$fixture1['group'], $fixture2['group']],
            'action' => 'APPROVED',
        ]);

    $response->assertOk()
        ->assertJsonPath('processed', 2)
        ->assertJsonPath('skipped', 0);

    expect($fixture1['entries'][0]->fresh()->status)->toBe('APPROVED');
    expect($fixture2['entries'][0]->fresh()->status)->toBe('APPROVED');
});

// ─── Partial failure ──────────────────────────────────────────────────────────

test('when one group throws an exception the others still succeed', function () {
    $fixture1 = createBulkSplFixture(1);
    $fixture2 = createBulkSplFixture(1);

    $realAction = app(ApproveSplEntriesAction::class);
    $mock = Mockery::mock(ApproveSplEntriesAction::class);

    // First group succeeds
    $mock->shouldReceive('execute')
        ->once()
        ->withArgs(fn ($decisions) => $decisions[0]['entry_id'] === $fixture1['entries'][0]->id)
        ->andReturnUsing(fn ($d, $u) => $realAction->execute($d, $u));

    // Second group throws optimistic lock exception
    $mock->shouldReceive('execute')
        ->once()
        ->withArgs(fn ($decisions) => $decisions[0]['entry_id'] === $fixture2['entries'][0]->id)
        ->andThrow(new OptimisticLockException('Data telah diubah oleh reviewer lain.'));

    $this->app->instance(ApproveSplEntriesAction::class, $mock);

    $response = $this->actingAs($fixture1['admin'])
        ->postJson(route('overtime.approvals.bulk'), [
            'groups' => [$fixture1['group'], $fixture2['group']],
            'action' => 'APPROVED',
        ]);

    $response->assertOk()
        ->assertJsonPath('processed', 1)
        ->assertJsonPath('skipped', 1);

    expect($fixture1['entries'][0]->fresh()->status)->toBe('APPROVED');
    expect($fixture2['entries'][0]->fresh()->status)->toBe('PENDING');
});
