<?php

use App\Models\CapexProject;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Section;
use App\Models\User;

test('team leader can switch between form and history via navigation tabs', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_BRW_HIST',
        'name' => 'Machining Plant',
        'default_hourly_rate' => 35000.00,
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_BRW_CRANK',
        'name' => 'Crankshaft Line',
        'is_active' => true,
    ]);

    User::factory()->teamLeader($section->id, $dept->id)->create([
        'email' => 'tl.machining@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'tl.machining@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('Overtime Entry')
        ->assertPathIs('/overtime/submissions/create')
        ->assertSee('Overtime Entry Form')
        // Switch to history tab
        ->click('[data-test="tab-history-link"]')
        ->assertPathIs('/overtime/submissions')
        ->assertSee('Riwayat Pengajuan')
        // Switch back to form tab
        ->click('[data-test="tab-form-create"]')
        ->assertPathIs('/overtime/submissions/create')
        ->assertSee('Overtime Entry Form');
});

test('team leader can populate roster, input hours, and submit overtime batch', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_BRW_WLD',
        'name' => 'Welding Plant',
        'default_hourly_rate' => 32000.00,
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_BRW_UNDER',
        'name' => 'Underbody Welding Line',
        'is_active' => true,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create([
        'email' => 'tl.welding@factory.com',
        'password' => 'password',
    ]);

    $emp1 = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'npk' => 'EMP-60001',
        'full_name' => 'Hendro Setiawan',
        'hourly_rate' => 40000.00,
        'is_active' => true,
    ]);

    $emp2 = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'npk' => 'EMP-60002',
        'full_name' => 'Yusuf Maulana',
        'hourly_rate' => null,
        'is_active' => true,
    ]);

    $capex = CapexProject::create([
        'project_code' => 'CIP-BRW-01',
        'name' => 'Robotic Weld Jig Automation',
        'department_id' => $dept->id,
        'allocated_labor_hours' => 100.00,
        'allocated_labor_budget_idr' => 4000000.00,
        'physical_progress_pct' => 15.00,
        'status' => 'ACTIVE',
        'start_date' => '2026-01-01',
        'target_end_date' => '2026-12-31',
    ]);

    visit('/login')
        ->fill('email', 'tl.welding@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('Overtime Entry')
        ->assertPathIs('/overtime/submissions/create')
        // Populate all workers with 1 click
        ->click('[data-test="btn-empty-add-all"]')
        ->assertSee('EMP-60001')
        ->assertSee('EMP-60002')
        ->fill('[data-test="input-prod-'.$emp1->id.'"]', '2.5')
        ->fill('[data-test="input-prod-'.$emp2->id.'"]', '3.0')
        ->assertSee('5.5')
        ->click('[data-test="btn-submit-overtime"]')
        // Assert celebratory success card
        ->assertSee('Overtime Request Submitted Successfully!')
        ->assertSee('SPKL: Pending Attachment (Non-blocking BR-05)');

    $this->assertDatabaseHas('overtime_submissions', [
        'section_id' => $section->id,
        'status' => 'SUBMITTED',
    ]);

    $this->assertDatabaseHas('overtime_items', [
        'employee_id' => $emp1->id,
        'hours_production' => 2.50,
    ]);

    $this->assertDatabaseHas('spkl_documents', [
        'status' => 'PENDING',
    ]);
});

test('form retains entered rows upon validation failure (zero data loss)', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_BRW_ERR',
        'name' => 'Assembly Plant',
        'default_hourly_rate' => 30000.00,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_BRW_TRIM',
        'name' => 'Trim Line 1',
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create([
        'email' => 'tl.assembly@factory.com',
        'password' => 'password',
    ]);

    $emp1 = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'npk' => 'EMP-70001',
        'full_name' => 'Supriadi Kusuma',
    ]);

    visit('/login')
        ->fill('email', 'tl.assembly@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('Overtime Entry')
        ->click('[data-test="btn-empty-add-all"]')
        ->assertSee('EMP-70001')
        // Submit with 0 hours (violates BR-01: minimum 0.5 hours)
        ->click('[data-test="btn-submit-overtime"]')
        // Form retains entered worker row
        ->assertSee('EMP-70001')
        ->assertSee('Supriadi Kusuma')
        ->assertSee('BR-01');
});
