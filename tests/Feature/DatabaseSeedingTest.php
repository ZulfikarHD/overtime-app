<?php

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeBudget;
use App\Models\PolicyThreshold;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('database seeder orchestrates all seeders cleanly and completes well within 30 seconds', function () {
    $startTime = microtime(true);

    $this->seed(DatabaseSeeder::class);

    $elapsedSeconds = microtime(true) - $startTime;

    expect($elapsedSeconds)->toBeLessThan(30.0);
});

test('departments and sections are seeded with realistic automotive plant master data', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Department::count())->toBeGreaterThanOrEqual(6)
        ->and(Section::count())->toBeGreaterThanOrEqual(12);

    $stampingDept = Department::where('code', 'DEPT_STP')->first();
    expect($stampingDept)->not->toBeNull()
        ->and($stampingDept->sections()->count())->toBeGreaterThanOrEqual(3);

    $assemblyDept = Department::where('code', 'DEPT_ASY')->first();
    expect($assemblyDept)->not->toBeNull()
        ->and($assemblyDept->sections()->count())->toBeGreaterThanOrEqual(3);
});

test('employees are seeded with at least 100 records and unique NPK numbers', function () {
    $this->seed(DatabaseSeeder::class);

    $totalEmployees = Employee::count();
    $uniqueNpks = Employee::distinct('npk')->count();

    expect($totalEmployees)->toBeGreaterThanOrEqual(100)
        ->and($uniqueNpks)->toBe($totalEmployees);

    $sampleEmployee = Employee::first();
    expect($sampleEmployee)->not->toBeNull()
        ->and($sampleEmployee->department_id)->not->toBeNull()
        ->and($sampleEmployee->section_id)->not->toBeNull()
        ->and((float) $sampleEmployee->hourly_rate)->toBeGreaterThan(0);
});

test('users are seeded with required minimum role counts and valid credentials', function () {
    $this->seed(DatabaseSeeder::class);

    $adminCount = User::where('role', UserRole::Admin)->count();
    $managerCount = User::where('role', UserRole::Manager)->count();
    $teamLeaderCount = User::where('role', UserRole::TeamLeader)->count();
    $userCount = User::where('role', UserRole::User)->count();

    expect($adminCount)->toBeGreaterThanOrEqual(1)
        ->and($managerCount)->toBeGreaterThanOrEqual(2)
        ->and($teamLeaderCount)->toBeGreaterThanOrEqual(5)
        ->and($userCount)->toBeGreaterThanOrEqual(20);

    // Verify demo admin
    $admin = User::where('email', 'admin@factory.com')->first();
    expect($admin)->not->toBeNull()
        ->and($admin->role)->toBe(UserRole::Admin);

    // Verify demo manager has department assignment
    $manager = User::where('email', 'manager.assembly@factory.com')->first();
    expect($manager)->not->toBeNull()
        ->and($manager->department_id)->not->toBeNull();

    // Verify demo team leader has section assignment
    $teamLeader = User::where('email', 'tl.stamping.press@factory.com')->first();
    expect($teamLeader)->not->toBeNull()
        ->and($teamLeader->section_id)->not->toBeNull();
});

test('operational calendars are seeded for full year 2026 with HKN and HLR classifications', function () {
    $this->seed(DatabaseSeeder::class);

    $totalDays = OperationalCalendar::count();
    expect($totalDays)->toBe(365);

    // Verify standard Monday is HKN
    $monday = OperationalCalendar::find('2026-01-05');
    expect($monday)->not->toBeNull()
        ->and($monday->day_type)->toBe('HKN')
        ->and($monday->is_holiday)->toBeFalse();

    // Verify New Year is HLR holiday
    $newYear = OperationalCalendar::find('2026-01-01');
    expect($newYear)->not->toBeNull()
        ->and($newYear->day_type)->toBe('HLR')
        ->and($newYear->is_holiday)->toBeTrue();

    // Verify Sunday is HLR
    $sunday = OperationalCalendar::find('2026-01-04');
    expect($sunday)->not->toBeNull()
        ->and($sunday->day_type)->toBe('HLR');
});

test('policy thresholds and overtime budgets are seeded properly', function () {
    $this->seed(DatabaseSeeder::class);

    // Plant default threshold
    $plantDefault = PolicyThreshold::whereNull('department_id')->first();
    expect($plantDefault)->not->toBeNull()
        ->and((float) $plantDefault->weekly_soft_limit_hours)->toBe(20.0)
        ->and($plantDefault->spkl_grace_period_days)->toBe(2);

    // Overtime budgets exist for current period
    $budgetsCount = OvertimeBudget::where('fiscal_year', 2026)->where('fiscal_month', 9)->count();
    expect($budgetsCount)->toBeGreaterThanOrEqual(1);

    $sampleBudget = OvertimeBudget::whereNotNull('section_id')->first();
    expect($sampleBudget)->not->toBeNull()
        ->and((float) $sampleBudget->planned_hours)->toBeGreaterThan(0)
        ->and((float) $sampleBudget->week1_planned_hours)->toBeGreaterThan(0);
});
