<?php

use App\Models\Department;
use App\Models\Section;
use App\Models\User;

test('admin can navigate from sidebar to master data hub and view hierarchy', function () {
    $dept = Department::create([
        'code' => 'DEPT_BRW_STP',
        'name' => 'Stamping Plant Division',
        'cost_center_code' => 'CC-STP-880',
        'default_hourly_rate' => 38500.00,
        'is_active' => true,
    ]);

    Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_BRW_PRESS',
        'name' => 'Transfer Press Line 1',
        'is_active' => true,
    ]);

    $admin = User::factory()->admin()->create([
        'name' => 'Admin Plant Manager',
        'email' => 'admin.hierarchy@factory.com',
        'password' => 'password',
        'npk' => 'ADM-70001',
    ]);

    visit('/login')
        ->fill('email', 'admin.hierarchy@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->assertSee('Master Data')
        ->click('Master Data')
        ->assertPathIs('/admin/master-data')
        ->assertSee('Master Data Hub')
        ->assertSee('Departments & Sections')
        ->assertSee('DEPT_BRW_STP')
        ->assertSee('Stamping Plant Division')
        ->assertSee('CC-STP-880')
        ->assertSee('SEC_BRW_PRESS')
        ->assertSee('Transfer Press Line 1');
});

test('admin can create department and nested section through modal dialogs', function () {
    $admin = User::factory()->admin()->create([
        'name' => 'Super Administrator',
        'email' => 'super.admin@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'super.admin@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('Master Data')
        ->assertPathIs('/admin/master-data')
        ->click('[data-test="btn-add-department"]')
        ->assertSee('Add Department')
        ->fill('#dept-code', 'DEPT_INSPECT')
        ->fill('#dept-name', 'Quality Inspection Unit')
        ->fill('#dept-cost-center', 'CC-QAC-777')
        ->fill('#dept-rate', '42500')
        ->click('Save')
        ->assertSee('DEPT_INSPECT')
        ->assertSee('Quality Inspection Unit')
        ->assertSee('CC-QAC-777')
        ->click('[data-test="btn-add-section-DEPT_INSPECT"]')
        ->assertSee('Add Section')
        ->assertSee('Quality Inspection Unit')
        ->fill('#section-code', 'SEC_METROLOGY')
        ->fill('#section-name', 'Precision Metrology Lab')
        ->click('Save')
        ->assertSee('SEC_METROLOGY')
        ->assertSee('Precision Metrology Lab');
});

test('admin can edit department with locked immutable code and update values', function () {
    $dept = Department::create([
        'code' => 'DEPT_TO_EDIT',
        'name' => 'Initial Dept Name',
        'cost_center_code' => 'CC-EDIT-1',
        'default_hourly_rate' => 30000.00,
        'is_active' => true,
    ]);

    $admin = User::factory()->admin()->create([
        'email' => 'editor.admin@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'editor.admin@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->click('Master Data')
        ->assertSee('Initial Dept Name')
        ->click('[data-test="btn-edit-dept-DEPT_TO_EDIT"]')
        ->assertSee('Edit Department')
        ->assertSee('Code is permanently locked after creation (BR-03).')
        ->fill('#dept-name', 'Renamed Stamping Dept')
        ->fill('#dept-cost-center', 'CC-EDIT-2')
        ->fill('#dept-rate', '35000')
        ->click('Save')
        ->assertSee('Renamed Stamping Dept')
        ->assertSee('CC-EDIT-2');
});

test('deactivating department with active child sections triggers integrity warning dialog', function () {
    $dept = Department::create([
        'code' => 'DEPT_GUARD_BRW',
        'name' => 'Guarded Division',
        'cost_center_code' => 'CC-GRD-BRW',
        'default_hourly_rate' => 30000.00,
        'is_active' => true,
    ]);

    Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_ACTIVE_SUB',
        'name' => 'Active Sub-line',
        'is_active' => true,
    ]);

    $admin = User::factory()->admin()->create([
        'email' => 'guard.admin@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'guard.admin@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->click('Master Data')
        ->assertSee('Guarded Division')
        ->click('[data-test="btn-toggle-dept-DEPT_GUARD_BRW"]')
        ->assertSee('Cannot Deactivate Department')
        ->assertSee('active section(s)')
        ->click('Understood');
});

test('submitting duplicate department code displays validation error', function () {
    Department::create([
        'code' => 'DEPT_EXISTING',
        'name' => 'Existing Division',
        'cost_center_code' => 'CC-EXT-1',
        'default_hourly_rate' => 30000.00,
        'is_active' => true,
    ]);

    $admin = User::factory()->admin()->create([
        'email' => 'dup.admin@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'dup.admin@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->click('Master Data')
        ->click('[data-test="btn-add-department"]')
        ->fill('#dept-code', 'DEPT_EXISTING')
        ->fill('#dept-name', 'Another Division')
        ->fill('#dept-cost-center', 'CC-EXT-2')
        ->fill('#dept-rate', '30000')
        ->click('Save')
        ->assertSee('Department code has already been registered.');
});
