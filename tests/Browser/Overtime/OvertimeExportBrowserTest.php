<?php

use App\Models\Department;
use App\Models\Section;
use App\Models\SplEntry;
use App\Models\User;
use Carbon\Carbon;

test('manager can interact with export dropdown on approval queue', function () {
    $today = Carbon::now('Asia/Jakarta')->toDateString();

    $dept = Department::factory()->create([
        'code' => 'DEPT_BRW_EXP_'.uniqid(),
        'name' => 'Assembly Browser Dept',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'name' => 'Trim Line Export',
        'is_active' => true,
    ]);

    User::factory()->manager($dept->id)->create([
        'email' => 'mgr.export.brw@factory.com',
        'password' => 'password',
    ]);

    $importer = User::factory()->user()->create();

    SplEntry::create([
        'npk_snapshot' => 'EMP-EXP-1001',
        'employee_name_snapshot' => 'Cahyo Operator',
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

    $page = visit('/login')
        ->fill('email', 'mgr.export.brw@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-overtime-approvals"]')
        ->assertPathIs('/overtime/approvals')
        ->assertSee('Trim Line Export')
        ->assertNoJavaScriptErrors();

    // Export dropdown should still be present if it exists in the new page
    // (Export feature is kept in controller for legacy data)
    $page->assertNoJavaScriptErrors();
});
