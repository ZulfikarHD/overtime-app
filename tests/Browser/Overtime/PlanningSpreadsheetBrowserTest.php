<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Section;
use App\Models\User;

test('planning spreadsheet supports excel-style cell entry and shows monitoring section', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_PLAN_XL',
        'name' => 'Planning Excel Dept',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_PLAN_XL',
        'name' => 'Planning Excel Section',
        'is_active' => true,
    ]);

    User::factory()->admin()->create([
        'email' => 'admin.planning.xl@factory.com',
        'password' => 'password',
        'department_id' => $dept->id,
    ]);

    Employee::factory()->create([
        'section_id' => $section->id,
        'department_id' => $dept->id,
        'npk' => 'XL-1001',
        'full_name' => 'Budi Santoso XL',
        'is_active' => true,
    ]);

    visit('/login')
        ->fill('email', 'admin.planning.xl@factory.com')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/dashboard')
        ->navigate('/overtime/planning/create?section_id='.$section->id.'&fiscal_year=2026&fiscal_month=9')
        ->assertSee('Budi Santoso XL')
        ->assertPresent('[data-test="period-year"]')
        ->assertPresent('[data-test="period-month"]')
        ->assertPresent('[data-test="planning-spreadsheet"]')
        ->assertPresent('[data-test="col-total-jam"]')
        ->assertPresent('[data-test="planning-monitoring"]')
        ->assertPresent('[data-test="tab-monitor-weekly"]')
        ->assertPresent('[data-test="col-conversi-idx"]')
        ->assertPresent('[data-test="col-week-hours"]')
        ->assertPresent('[data-test="col-gt-hour"]')
        ->click('[data-test="tab-monitor-pva"]')
        ->assertPresent('[data-test="col-plan-vs-actual"]')
        ->click('#plan-cell-0-0')
        ->type('#plan-cell-0-0', '2')
        ->keys('#plan-cell-0-0', 'Tab')
        ->assertNoJavaScriptErrors();
});
