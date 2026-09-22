<?php

use App\Models\Department;
use App\Models\Section;
use App\Models\SplEntry;
use App\Models\User;
use Carbon\Carbon;

/**
 * NOTE: The audit trail drawer was part of the old OvertimeItem approval flow.
 * The new approval flow (SPL-based) tracks reviewed_at/reviewed_by directly on SplEntry.
 * These tests verify the approval queue page loads correctly with SPL data
 * until a dedicated audit trail for SplEntry is implemented.
 */
test('manager can open approval queue with spl entries and view the page', function () {
    $today = Carbon::now('Asia/Jakarta')->toDateString();

    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create(['department_id' => $dept->id, 'is_active' => true]);
    $importer = User::factory()->user()->create();

    User::factory()->manager($dept->id)->create([
        'email' => 'mgr.audit.brw@factory.com',
        'password' => 'password',
    ]);

    SplEntry::create([
        'npk_snapshot' => 'EMP-AUDIT-001',
        'employee_name_snapshot' => 'Dedi Audit Worker',
        'section_id' => $section->id,
        'department_id' => $dept->id,
        'section_name_snapshot' => $section->name,
        'department_name_snapshot' => $dept->name,
        'realization_date' => $today,
        'day_type' => 'HKN',
        'start_time' => '18:00:00',
        'end_time' => '22:00:00',
        'total_hours' => 4.00,
        'status' => 'PENDING',
        'lock_version' => 0,
        'imported_by_user_id' => $importer->id,
    ]);

    visit('/login')
        ->fill('email', 'mgr.audit.brw@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-overtime-approvals"]')
        ->assertPathIs('/overtime/approvals')
        ->assertSee($section->name)
        ->assertNoJavaScriptErrors();
});
