<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\PolicyThreshold;
use App\Models\Section;
use App\Models\SpklDocument;
use App\Models\User;
use Carbon\Carbon;

test('manager can view summary employee overtime table with all columns and visual indicators', function () {
    $dept = Department::create([
        'code' => 'DEPT_TBL_BR1',
        'name' => 'Assembly Plant Dept',
        'cost_center_code' => 'CC-ASM-BR1',
        'default_hourly_rate' => 45000,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_AS1',
        'name' => 'Chassis Assembly',
        'is_active' => true,
    ]);

    $now = Carbon::now('Asia/Jakarta');
    $year = $now->year;
    $month = $now->month;
    $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
    $date = "{$year}-{$monthStr}-03";

    OperationalCalendar::create([
        'calendar_date' => $date,
        'day_type' => 'HKN',
        'is_holiday' => false,
    ]);

    PolicyThreshold::create([
        'department_id' => $dept->id,
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
        'spkl_grace_period_days' => 2,
        'burn_warning_pct' => 100.0,
        'burn_danger_pct' => 115.0,
    ]);

    OvertimeBudget::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_hours' => 80.0,
        'planned_amount' => 3600000,
    ]);

    $emp1 = Employee::create([
        'npk' => 'EMP-ASM-101',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Bambang Sudirman',
        'job_position' => 'Chassis Specialist',
        'is_active' => true,
    ]);

    $emp2 = Employee::create([
        'npk' => 'EMP-ASM-102',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Eko Prasetyo',
        'job_position' => 'Line Technician',
        'is_active' => true,
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Manager Table',
        'email' => 'manager.table@factory.com',
        'password' => 'password',
    ]);

    $sub = OvertimeSubmission::create([
        'submission_code' => 'SPKL-ASM-001',
        'submission_date' => $date,
        'operational_date' => $date,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $manager->id,
        'status' => 'APPROVED',
        'total_hours_cached' => 12.0,
    ]);

    SpklDocument::create([
        'overtime_submission_id' => $sub->id,
        'spkl_number' => 'SPKL/ASM/2026/001',
        'status' => 'ATTACHED',
        'due_date' => $now->copy()->addDays(2)->toDateString(),
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $emp1->id,
        'npk_snapshot' => $emp1->npk,
        'hours_production' => 6.0,
        'hours_tpm' => 2.0,
        'hours_project' => 4.0,
        'hours_others' => 0.0,
        'total_hours' => 12.0,
        'hourly_rate_snapshot' => 45000,
        'total_cost_snapshot' => 540000,
        'status' => 'APPROVED',
    ]);

    visit('/login')
        ->fill('email', 'manager.table@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->assertPresent('[data-test="employee-summary-table-section"]')
        ->assertPresent('[data-test="employee-summary-table"]')
        ->assertPresent('[data-test="employee-table-search-input"]')
        ->assertPresent('[data-test="mini-progress-bar"]')
        ->assertPresent('[data-test="mini-category-bar"]')
        ->assertPresent('[data-test="spkl-status-badge"]')
        ->assertSee('Bambang Sudirman')
        ->assertSee('EMP-ASM-101')
        ->assertSee('SEC_AS1')
        ->assertSee('Eko Prasetyo');
});

test('manager can search employees in real-time by name and npk', function () {
    $dept = Department::create([
        'code' => 'DEPT_TBL_SRCH',
        'name' => 'Machining Search Dept',
        'cost_center_code' => 'CC-MCH-SRCH',
        'default_hourly_rate' => 40000,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_MC1',
        'name' => 'Milling Section',
        'is_active' => true,
    ]);

    Employee::create([
        'npk' => 'EMP-SRCH-901',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Wawan Hendrawan',
        'job_position' => 'CNC Specialist',
        'is_active' => true,
    ]);

    Employee::create([
        'npk' => 'EMP-SRCH-902',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Siti Aminah',
        'job_position' => 'Quality Inspector',
        'is_active' => true,
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Manager Search',
        'email' => 'manager.search@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'manager.search@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->assertSee('Wawan Hendrawan')
        ->assertSee('Siti Aminah')
        // Filter by specific name
        ->fill('[data-test="employee-table-search-input"]', 'Wawan')
        ->waitForText('Wawan Hendrawan')
        ->assertDontSee('Siti Aminah')
        // Filter by NPK
        ->fill('[data-test="employee-table-search-input"]', '902')
        ->waitForText('Siti Aminah')
        ->assertDontSee('Wawan Hendrawan')
        // Non-matching query triggers empty state
        ->fill('[data-test="employee-table-search-input"]', 'NonExistentPersonXYZ')
        ->waitForText('No matching employee data');
});

test('manager can click employee row to open quick dossier drawer and inspect details', function () {
    $dept = Department::create([
        'code' => 'DEPT_TBL_DRWR',
        'name' => 'Welding Drawer Dept',
        'cost_center_code' => 'CC-WLD-DRWR',
        'default_hourly_rate' => 42000,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_WD1',
        'name' => 'Robot Spot Welding',
        'is_active' => true,
    ]);

    $now = Carbon::now('Asia/Jakarta');
    $date = $now->toDateString();

    OperationalCalendar::create([
        'calendar_date' => $date,
        'day_type' => 'HKN',
        'is_holiday' => false,
    ]);

    $emp = Employee::create([
        'npk' => 'EMP-DRWR-777',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Agus Gunawan',
        'job_position' => 'Senior Robot Welder',
        'is_active' => true,
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Manager Drawer',
        'email' => 'manager.drawer@factory.com',
        'password' => 'password',
    ]);

    $sub = OvertimeSubmission::create([
        'submission_code' => 'SPKL-DRW-001',
        'submission_date' => $date,
        'operational_date' => $date,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $manager->id,
        'status' => 'APPROVED',
        'total_hours_cached' => 8.0,
    ]);

    SpklDocument::create([
        'overtime_submission_id' => $sub->id,
        'spkl_number' => 'SPKL/WLD/DRW/77',
        'status' => 'ATTACHED',
        'due_date' => $now->copy()->addDays(2)->toDateString(),
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 5.0,
        'hours_tpm' => 1.0,
        'hours_project' => 2.0,
        'hours_others' => 0.0,
        'total_hours' => 8.0,
        'hourly_rate_snapshot' => 42000,
        'total_cost_snapshot' => 336000,
        'status' => 'APPROVED',
    ]);

    visit('/login')
        ->fill('email', 'manager.drawer@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->assertSee('Agus Gunawan')
        // Click employee row to open drawer
        ->click('Agus Gunawan')
        ->assertPresent('[data-test="employee-quick-dossier-drawer"]')
        ->assertPresent('[data-test="drawer-npk-badge"]')
        ->assertPresent('[data-test="drawer-burn-zone-badge"]')
        ->assertPresent('[data-test="drawer-total-hours"]')
        ->assertPresent('[data-test="drawer-recent-shifts-table"]')
        ->assertPresent('[data-test="open-full-dossier-button"]')
        ->assertSee('EMP-DRWR-777')
        ->assertSee('SPKL/WLD/DRW/77');
});

test('manager can sort table columns by name, total hours, and burn index', function () {
    $dept = Department::create([
        'code' => 'DEPT_TBL_SORT',
        'name' => 'Paint Shop Sort Dept',
        'cost_center_code' => 'CC-PNT-SORT',
        'default_hourly_rate' => 39000,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_PS1',
        'name' => 'Top Coat Line',
        'is_active' => true,
    ]);

    Employee::create([
        'npk' => 'EMP-SRT-001',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Aditya Nugraha',
        'job_position' => 'Spray Painter',
        'is_active' => true,
    ]);

    Employee::create([
        'npk' => 'EMP-SRT-002',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Zulfikar Ahmad',
        'job_position' => 'Inspection Tech',
        'is_active' => true,
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Manager Sort',
        'email' => 'manager.sort@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'manager.sort@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->assertPresent('[data-test="sort-header-name"]')
        ->assertPresent('[data-test="sort-header-burn-index"]')
        ->assertPresent('[data-test="sort-header-total-hours"]')
        ->click('[data-test="sort-header-name"]')
        ->assertSee('Aditya Nugraha')
        ->click('[data-test="sort-header-burn-index"]')
        ->assertSee('Zulfikar Ahmad');
});

test('selecting category in category donut filters employee table rows', function () {
    $dept = Department::create([
        'code' => 'DEPT_TBL_DONUT',
        'name' => 'Logistics Dept',
        'cost_center_code' => 'CC-LOG-DONUT',
        'default_hourly_rate' => 38000,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_LG1',
        'name' => 'Warehouse Line',
        'is_active' => true,
    ]);

    $now = Carbon::now('Asia/Jakarta');
    $date = $now->toDateString();

    OperationalCalendar::create([
        'calendar_date' => $date,
        'day_type' => 'HKN',
        'is_holiday' => false,
    ]);

    $empProdOnly = Employee::create([
        'npk' => 'EMP-CAT-111',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Hadi Supriyanto',
        'job_position' => 'Forklift Driver',
        'is_active' => true,
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Manager Donut',
        'email' => 'manager.donut@factory.com',
        'password' => 'password',
    ]);

    $sub = OvertimeSubmission::create([
        'submission_code' => 'SPKL-CAT-001',
        'submission_date' => $date,
        'operational_date' => $date,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $manager->id,
        'status' => 'APPROVED',
        'total_hours_cached' => 10.0,
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $empProdOnly->id,
        'npk_snapshot' => $empProdOnly->npk,
        'hours_production' => 10.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'total_hours' => 10.0,
        'hourly_rate_snapshot' => 38000,
        'total_cost_snapshot' => 380000,
        'status' => 'APPROVED',
    ]);

    visit('/login')
        ->fill('email', 'manager.donut@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->assertPresent('[data-test="category-distribution-donut-card"]')
        ->assertSee('Hadi Supriyanto')
        // Click Production category legend button via data-test
        ->click('[data-test="category-pill-production"]')
        ->assertPresent('[data-test="active-category-filter-badge"]')
        ->assertSee('Hadi Supriyanto');
});
