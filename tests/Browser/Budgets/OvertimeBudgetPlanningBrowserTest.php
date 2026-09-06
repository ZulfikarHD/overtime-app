<?php

use App\Models\Department;
use App\Models\Section;
use App\Models\User;

test('admin can navigate to budget planning and set section overtime budget with sheet', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_BRW_BUDGET',
        'name' => 'Painting & Finishing',
        'default_hourly_rate' => 35000.00,
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_PAINT_TOP',
        'name' => 'Top Coat Booth',
        'is_active' => true,
    ]);

    $admin = User::factory()->admin()->create([
        'email' => 'budget.admin@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'budget.admin@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('Budget Planning')
        ->assertPathIs('/budgets/planning')
        ->assertSee('SEC_PAINT_TOP')
        ->assertSee('Top Coat Booth')
        ->click('[data-test="btn-edit-budget-SEC_PAINT_TOP"]')
        ->fill('[data-test="input-planned-hours"]', '160')
        ->click('[data-test="btn-save-budget"]')
        ->assertSee('SEC_PAINT_TOP')
        ->assertSee('160');

    $this->assertDatabaseHas('overtime_budgets', [
        'section_id' => $section->id,
        'department_id' => $dept->id,
        'planned_hours' => 160.0,
    ]);
});

test('manager sees only own department and can open csv import sheet', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_BRW_MGR',
        'name' => 'Logistics Department',
        'default_hourly_rate' => 28000.00,
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_WH_RECEIVE',
        'name' => 'Receiving Warehouse',
        'is_active' => true,
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'email' => 'budget.manager@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'budget.manager@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('Budget Planning')
        ->assertPathIs('/budgets/planning')
        ->assertSee('SEC_WH_RECEIVE')
        ->click('[data-test="btn-open-csv-import"]')
        ->assertSee('section_code, fiscal_year, fiscal_month, planned_hours');
});
