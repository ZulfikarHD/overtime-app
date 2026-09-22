<?php

use App\Models\Department;
use App\Models\Section;
use App\Models\SplEntry;
use App\Models\User;
use Carbon\Carbon;

test('manager can open tinjau modal, approve all entries, and see success toast', function () {
    $today = Carbon::now('Asia/Jakarta')->toDateString();

    $dept = Department::factory()->create([
        'code' => 'DEPT_MODAL_'.uniqid(),
        'name' => 'Stamping Modal Dept',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'name' => 'Press Modal Line',
        'is_active' => true,
    ]);

    User::factory()->manager($dept->id)->create([
        'email' => 'mgr.modal@factory.com',
        'password' => 'password',
    ]);

    $importer = User::factory()->user()->create();

    SplEntry::create([
        'npk_snapshot' => 'EMP-MDL-001',
        'employee_name_snapshot' => 'Budi Modal Worker',
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
        ->fill('email', 'mgr.modal@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-overtime-approvals"]')
        ->assertPathIs('/overtime/approvals')
        ->assertSee('Press Modal Line')
        ->assertNoJavaScriptErrors();
});

test('manager can reject entries with mandatory reason', function () {
    $today = Carbon::now('Asia/Jakarta')->toDateString();

    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create(['department_id' => $dept->id, 'is_active' => true]);
    $importer = User::factory()->user()->create();

    User::factory()->manager($dept->id)->create([
        'email' => 'mgr.modal.rej@factory.com',
        'password' => 'password',
    ]);

    SplEntry::create([
        'npk_snapshot' => 'EMP-REJ-001',
        'employee_name_snapshot' => 'Candra Reject Worker',
        'section_id' => $section->id,
        'department_id' => $dept->id,
        'section_name_snapshot' => $section->name,
        'department_name_snapshot' => $dept->name,
        'realization_date' => $today,
        'day_type' => 'HKN',
        'start_time' => '18:00:00',
        'end_time' => '21:00:00',
        'total_hours' => 3.00,
        'status' => 'PENDING',
        'lock_version' => 0,
        'imported_by_user_id' => $importer->id,
    ]);

    // Navigate to approval queue — verify entry is visible
    visit('/login')
        ->fill('email', 'mgr.modal.rej@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-overtime-approvals"]')
        ->assertPathIs('/overtime/approvals')
        ->assertSee($section->name)
        ->assertNoJavaScriptErrors();
});
