<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Section;
use App\Models\User;

test('team leader can navigate to employee reports, search by name, view dossier header and use recent lookups', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_BRW_ASY',
        'name' => 'Assembly Department',
        'is_active' => true,
    ]);

    $sectionA = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_BRW_TRIM',
        'name' => 'Trim & Chassis Line',
        'is_active' => true,
    ]);

    $sectionB = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_BRW_DOOR',
        'name' => 'Door Sub-Line',
        'is_active' => true,
    ]);

    $employeeA = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $sectionA->id,
        'full_name' => 'Budi Santoso',
        'npk' => 'EMP-4091',
        'job_position' => 'Senior Line Operator',
        'hourly_rate' => 45000.00,
        'is_active' => true,
    ]);

    $employeeB = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $sectionB->id,
        'full_name' => 'Hendra Setiawan',
        'npk' => 'EMP-8822',
        'job_position' => 'Door Assembler',
        'is_active' => true,
    ]);

    $teamLeader = User::factory()->teamLeader($sectionA->id, $dept->id)->create([
        'name' => 'Slamet Supervisor',
        'email' => 'slamet.tl@factory.com',
        'password' => 'password',
        'npk' => 'EMP-TL-01',
    ]);

    visit('/login')
        ->fill('email', 'slamet.tl@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        // Click Laporan Karyawan sidebar item
        ->click('[data-test="nav-employee-reports"]')
        ->assertPathIs('/reports/employees')
        ->assertSee('Dossier Hub')
        ->assertSee('Budi Santoso')
        ->assertSee('EMP-4091')
        ->assertDontSee('Hendra Setiawan')
        // Use live debounced search
        ->fill('[data-test="employee-search-input"]', 'Budi')
        ->waitForText('Senior Line Operator')
        ->assertSee('Trim & Chassis Line')
        // Click search result to navigate to dossier
        ->click('[data-test="employee-search-item"]')
        ->assertPathIs('/reports/employees/EMP-4091')
        ->assertSee('Budi Santoso')
        ->assertSee('EMP-4091')
        ->assertSee('Senior Line Operator')
        ->assertSee('Trim & Chassis Line')
        ->assertSee('Assembly Department')
        // Switch tab to Timesheet
        ->click('[data-test="tab-timesheet"]')
        ->assertPresent('[data-test="personal-timesheet-section"]')
        // Navigate back to Roster
        ->click('[data-test="dossier-header"] a')
        ->assertPathIs('/reports/employees')
        // Verify Recent Lookups pill is present
        ->waitForText('Recent Lookups')
        ->assertSee('Budi Santoso')
        // Click Recent Lookup pill to return to dossier
        ->click('[data-test="recent-lookup-pill"]')
        ->assertPathIs('/reports/employees/EMP-4091')
        ->assertSee('Budi Santoso');
});

test('manager can search employee across departments and scoped search excludes unauthorized workers', function () {
    $deptA = Department::factory()->create([
        'code' => 'DEPT_MGR_WLD',
        'name' => 'Welding Plant',
        'is_active' => true,
    ]);

    $deptB = Department::factory()->create([
        'code' => 'DEPT_MGR_PNT',
        'name' => 'Paint Plant',
        'is_active' => true,
    ]);

    $secA = Section::factory()->create(['department_id' => $deptA->id, 'name' => 'Robotic Welding']);
    $secB = Section::factory()->create(['department_id' => $deptB->id, 'name' => 'Primer Coating']);

    Employee::factory()->create([
        'department_id' => $deptA->id,
        'section_id' => $secA->id,
        'full_name' => 'Wawan Darmawan',
        'npk' => 'EMP-WAW-01',
        'job_position' => 'Robotics Specialist',
    ]);

    Employee::factory()->create([
        'department_id' => $deptB->id,
        'section_id' => $secB->id,
        'full_name' => 'Wawan Paintman',
        'npk' => 'EMP-WAW-99',
        'job_position' => 'Spray Booth Operator',
    ]);

    $manager = User::factory()->manager($deptA->id)->create([
        'name' => 'Ir. Hartono Manager',
        'email' => 'hartono.mgr@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'hartono.mgr@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-employee-reports"]')
        ->assertPathIs('/reports/employees')
        ->assertSee('Wawan Darmawan')
        ->assertDontSee('Wawan Paintman')
        ->fill('[data-test="employee-search-input"]', 'Wawan')
        ->waitForText('Robotics Specialist')
        ->assertSee('EMP-WAW-01')
        ->assertDontSee('Spray Booth Operator');
});
