<?php

use App\Models\Department;
use App\Models\Section;
use App\Models\SplEntry;
use App\Models\User;
use Carbon\Carbon;

function createBrowserSplEntry(Department $dept, Section $section, User $importer, string $date, array $extra = []): SplEntry
{
    return SplEntry::create(array_merge([
        'npk_snapshot' => 'EMP-'.fake()->unique()->numerify('#####'),
        'employee_name_snapshot' => fake()->name(),
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
    ], $extra));
}

test('manager can open approval queue from sidebar and see SPL group row', function () {
    $today = Carbon::now('Asia/Jakarta')->toDateString();

    $dept = Department::factory()->create([
        'code' => 'DEPT_BRW_AQ_'.uniqid(),
        'name' => 'Stamping Browser Dept',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_BRW_PRESS_'.uniqid(),
        'name' => 'Press Browser Line',
        'is_active' => true,
    ]);

    User::factory()->manager($dept->id)->create([
        'name' => 'Manager Approval Browser',
        'email' => 'mgr.approval.brw@factory.com',
        'password' => 'password',
    ]);

    $importer = User::factory()->user()->create();

    createBrowserSplEntry($dept, $section, $importer, $today, [
        'npk_snapshot' => 'EMP-AQ-9001',
        'employee_name_snapshot' => 'Budi Operator',
        'total_hours' => 5.00,
    ]);

    visit('/login')
        ->fill('email', 'mgr.approval.brw@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-overtime-approvals"]')
        ->assertPathIs('/overtime/approvals')
        ->assertSee('Press Browser Line')
        ->assertSee('Stamping Browser Dept')
        ->assertPresent('[data-test="pending-count-pill"]')
        ->assertNoJavaScriptErrors();
});

test('team leader does not see approval queue sidebar item', function () {
    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create(['department_id' => $dept->id, 'is_active' => true]);

    User::factory()->teamLeader($section->id, $dept->id)->create([
        'email' => 'tl.noapproval.brw@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'tl.noapproval.brw@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->assertMissing('[data-test="nav-overtime-approvals"]')
        ->assertNoJavaScriptErrors();
});

test('manager can switch status tabs and see empty state for unmatched filter', function () {
    $today = Carbon::now('Asia/Jakarta')->toDateString();

    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create(['department_id' => $dept->id, 'is_active' => true]);
    $importer = User::factory()->user()->create();

    User::factory()->manager($dept->id)->create([
        'email' => 'mgr.tabs.brw@factory.com',
        'password' => 'password',
    ]);

    createBrowserSplEntry($dept, $section, $importer, $today);

    visit('/login')
        ->fill('email', 'mgr.tabs.brw@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-overtime-approvals"]')
        ->assertPathIs('/overtime/approvals')
        ->assertSee($section->name)
        ->click('[data-test="status-tab-approved"]')
        ->assertPresent('[data-test="approval-empty-state"]')
        ->click('[data-test="status-tab-pending"]')
        ->assertSee($section->name)
        ->assertNoJavaScriptErrors();
});
