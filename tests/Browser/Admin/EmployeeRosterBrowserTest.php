<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Section;
use App\Models\User;

test('admin can navigate to master data hub and view employees tab with roster table', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_ROSTER_BRW',
        'name' => 'Powertrain Division',
        'default_hourly_rate' => 45000.00,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_MACHINING',
        'name' => 'Engine Machining Line',
    ]);

    Employee::factory()->create([
        'npk' => 'EMP-ROSTER-01',
        'full_name' => 'Bambang Pamungkas',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'job_position' => 'CNC Machinist',
        'hourly_rate' => 48000.00,
        'is_active' => true,
    ]);

    $admin = User::factory()->admin()->create([
        'email' => 'admin.roster@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'admin.roster@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('Master Data')
        ->assertPathIs('/admin/master-data')
        ->click('[data-test="tab-employees"]')
        ->assertSee('EMP-ROSTER-01')
        ->assertSee('Bambang Pamungkas')
        ->assertSee('DEPT_ROSTER_BRW')
        ->assertSee('SEC_MACHINING')
        ->assertSee('CNC Machinist');
});

test('admin can create a new employee via slide-in drawer sheet', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_STAMPING',
        'name' => 'Stamping Plant',
        'default_hourly_rate' => 40000.00,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_BLANKING',
        'name' => 'Blanking Line',
    ]);

    $admin = User::factory()->admin()->create([
        'email' => 'admin.create.emp@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'admin.create.emp@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('Master Data')
        ->assertPathIs('/admin/master-data')
        ->click('[data-test="tab-employees"]')
        ->click('[data-test="btn-add-employee"]')
        ->assertSee('NPK')
        ->fill('#emp-npk', 'EMP-NEW-777')
        ->fill('#emp-name', 'Hendro Siswanto')
        ->select('#emp-dept', (string) $dept->id)
        ->select('#emp-section', (string) $section->id)
        ->fill('#emp-position', 'Press Feeder Operator')
        ->fill('#emp-hourly-rate', '42000')
        ->click('[data-test="btn-save-employee"]')
        ->assertSee('EMP-NEW-777')
        ->assertSee('Hendro Siswanto')
        ->assertSee('Press Feeder Operator');
});

test('admin can edit employee with locked immutable NPK (BR-03)', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);

    $emp = Employee::factory()->create([
        'npk' => 'EMP-LOCKED-01',
        'full_name' => 'Original Name Worker',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'job_position' => 'Junior Operator',
        'hourly_rate' => 38000.00,
    ]);

    $admin = User::factory()->admin()->create([
        'email' => 'admin.edit.emp@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'admin.edit.emp@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('Master Data')
        ->assertPathIs('/admin/master-data')
        ->click('[data-test="tab-employees"]')
        ->assertSee('EMP-LOCKED-01')
        ->click('[data-test="btn-edit-emp-EMP-LOCKED-01"]')
        ->assertSee('BR-03')
        ->fill('#emp-name', 'Promoted Senior Worker')
        ->fill('#emp-position', 'Senior Line Operator')
        ->click('[data-test="btn-save-employee"]')
        ->assertSee('EMP-LOCKED-01')
        ->assertSee('Promoted Senior Worker')
        ->assertSee('Senior Line Operator');
});

test('admin can filter employee roster using search input', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);

    Employee::factory()->create([
        'npk' => 'EMP-SEARCH-ALPHA',
        'full_name' => 'Alfa Romeo Worker',
        'department_id' => $dept->id,
        'section_id' => $section->id,
    ]);

    Employee::factory()->create([
        'npk' => 'EMP-SEARCH-BETA',
        'full_name' => 'Beta Operator',
        'department_id' => $dept->id,
        'section_id' => $section->id,
    ]);

    $admin = User::factory()->admin()->create([
        'email' => 'admin.search.emp@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'admin.search.emp@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('Master Data')
        ->assertPathIs('/admin/master-data')
        ->click('[data-test="tab-employees"]')
        ->assertSee('Alfa Romeo Worker')
        ->assertSee('Beta Operator')
        ->fill('[data-test="input-search-employees"]', 'Romeo')
        ->assertSee('Alfa Romeo Worker')
        ->assertDontSee('Beta Operator');
});

test('admin can deactivate employee with confirmation dialog', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);

    $emp = Employee::factory()->create([
        'npk' => 'EMP-DEACT-01',
        'full_name' => 'Worker To Deactivate',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'is_active' => true,
    ]);

    $admin = User::factory()->admin()->create([
        'email' => 'admin.deact.emp@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'admin.deact.emp@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('Master Data')
        ->assertPathIs('/admin/master-data')
        ->click('[data-test="tab-employees"]')
        ->assertSee('EMP-DEACT-01')
        ->click('[data-test="btn-toggle-emp-EMP-DEACT-01"]')
        ->assertSee('Worker To Deactivate')
        ->click('[data-test="confirm-dialog-confirm-button"]')
        ->assertSee('Nonaktif');
});

test('admin can open csv import drawer, upload csv file, audit preview, and confirm import', function () {
    $dept = Department::factory()->create(['code' => 'ASSEMBLY', 'name' => 'Assembly Plant']);
    $section = Section::factory()->create(['department_id' => $dept->id, 'code' => 'TRIM_LINE', 'name' => 'Trim Line 1']);

    $tempCsv = tempnam(sys_get_temp_dir(), 'csv_import_').'.csv';
    file_put_contents($tempCsv, implode("\n", [
        'npk,full_name,department_code,section_code,job_position,hourly_rate',
        'EMP-IMPORT-88,Imported Worker One,ASSEMBLY,TRIM_LINE,Trim Specialist,39000',
    ]));

    $admin = User::factory()->admin()->create([
        'email' => 'admin.import.emp@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'admin.import.emp@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('Master Data')
        ->assertPathIs('/admin/master-data')
        ->click('[data-test="tab-employees"]')
        ->click('[data-test="btn-import-csv"]')
        ->assertSee('CSV')
        ->attach('[data-test="input-csv-file"]', $tempCsv)
        ->assertSee('EMP-IMPORT-88')
        ->assertSee('Imported Worker One')
        ->click('[data-test="btn-confirm-import"]')
        ->assertSee('EMP-IMPORT-88')
        ->assertSee('Imported Worker One');

    if (file_exists($tempCsv)) {
        unlink($tempCsv);
    }
});
