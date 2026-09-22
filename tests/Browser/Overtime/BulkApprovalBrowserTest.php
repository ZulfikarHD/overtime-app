<?php

use App\Models\Department;
use App\Models\Section;
use App\Models\SplEntry;
use App\Models\User;
use Carbon\Carbon;

function createBulkBrowserSplEntry(Department $dept, Section $section, User $importer, string $date, array $extra = []): SplEntry
{
    return SplEntry::create(array_merge([
        'npk_snapshot' => 'EMP-BLK-'.fake()->unique()->numerify('#####'),
        'employee_name_snapshot' => fake()->name(),
        'section_id' => $section->id,
        'department_id' => $dept->id,
        'section_name_snapshot' => $section->name,
        'department_name_snapshot' => $dept->name,
        'realization_date' => $date,
        'day_type' => 'HKN',
        'start_time' => '18:00:00',
        'end_time' => '22:00:00',
        'total_hours' => 3.50,
        'status' => 'PENDING',
        'lock_version' => 0,
        'imported_by_user_id' => $importer->id,
    ], $extra));
}

test('manager can select a group, see bulk bar, confirm bulk approval, and see success toast', function () {
    $today = Carbon::now('Asia/Jakarta')->toDateString();

    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'name' => 'Assembly Bulk Line',
        'is_active' => true,
    ]);
    $importer = User::factory()->user()->create();

    User::factory()->manager($dept->id)->create([
        'email' => 'mgr.bulk.brw@factory.com',
        'password' => 'password',
    ]);

    createBulkBrowserSplEntry($dept, $section, $importer, $today);

    $groupKey = "{$section->id}|{$today}";

    visit('/login')
        ->fill('email', 'mgr.bulk.brw@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-overtime-approvals"]')
        ->assertPathIs('/overtime/approvals')
        ->assertSee('Assembly Bulk Line')
        ->assertMissing('[data-test="floating-bulk-bar"]')
        ->check('[data-test="approval-row-checkbox-'.$groupKey.'"]')
        ->assertPresent('[data-test="floating-bulk-bar"]')
        ->assertSeeIn('[data-test="bulk-selected-count"]', '1')
        ->click('[data-test="btn-bulk-approve"]')
        ->assertPresent('[data-test="bulk-approval-confirm-modal"]')
        ->assertPresent('[data-test="bulk-modal-title"]')
        ->assertSee('Assembly Bulk Line')
        ->click('[data-test="btn-bulk-confirm"]')
        ->assertPresent('[data-test="bulk-action-result-toast"]')
        ->assertNoJavaScriptErrors();
});

test('manager can bulk reject with mandatory shared rejection reason', function () {
    $today = Carbon::now('Asia/Jakarta')->toDateString();

    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create(['department_id' => $dept->id, 'name' => 'Reject Bulk Line', 'is_active' => true]);
    $importer = User::factory()->user()->create();

    User::factory()->manager($dept->id)->create([
        'email' => 'mgr.bulkrej.brw@factory.com',
        'password' => 'password',
    ]);

    createBulkBrowserSplEntry($dept, $section, $importer, $today);

    $groupKey = "{$section->id}|{$today}";

    visit('/login')
        ->fill('email', 'mgr.bulkrej.brw@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-overtime-approvals"]')
        ->assertPathIs('/overtime/approvals')
        ->check('[data-test="approval-row-checkbox-'.$groupKey.'"]')
        ->click('[data-test="btn-bulk-reject"]')
        ->assertPresent('[data-test="bulk-approval-confirm-modal"]')
        ->assertPresent('[data-test="bulk-rejection-reason-container"]')
        ->fill('[data-test="bulk-rejection-reason-input"]', 'Target shift tercapai tanpa lembur')
        ->click('[data-test="btn-bulk-confirm"]')
        ->assertPresent('[data-test="bulk-action-result-toast"]')
        ->assertNoJavaScriptErrors();
});

test('manager can clear bulk selection', function () {
    $today = Carbon::now('Asia/Jakarta')->toDateString();

    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create(['department_id' => $dept->id, 'is_active' => true]);
    $importer = User::factory()->user()->create();

    User::factory()->manager($dept->id)->create([
        'email' => 'mgr.clear.brw@factory.com',
        'password' => 'password',
    ]);

    createBulkBrowserSplEntry($dept, $section, $importer, $today);

    $groupKey = "{$section->id}|{$today}";

    visit('/login')
        ->fill('email', 'mgr.clear.brw@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-overtime-approvals"]')
        ->assertPathIs('/overtime/approvals')
        ->check('[data-test="approval-row-checkbox-'.$groupKey.'"]')
        ->assertPresent('[data-test="floating-bulk-bar"]')
        ->click('[data-test="btn-bulk-clear"]')
        ->assertMissing('[data-test="floating-bulk-bar"]')
        ->assertNoJavaScriptErrors();
});
