<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\PolicyThreshold;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Creates one Merah alert for today: a budget of 10 h/day pacing with 10 h approved = 100 % (> 90 %).
 */
function earlyWarningBrowserMerahToday(Section $section): string
{
    $today = Carbon::now('Asia/Jakarta')->startOfDay();

    OvertimeBudget::factory()->forSection($section)->create([
        'fiscal_year' => $today->year,
        'fiscal_month' => $today->month,
        'planned_hours' => $today->daysInMonth * 10.0,
    ]);

    $submission = OvertimeSubmission::factory()->create([
        'department_id' => $section->department_id,
        'section_id' => $section->id,
        'operational_date' => $today->toDateString(),
        'submission_date' => $today->toDateString(),
    ]);

    OvertimeItem::factory()
        ->forEmployee(Employee::factory()->create())
        ->approved(User::factory()->admin()->create())
        ->create([
            'overtime_submission_id' => $submission->id,
            'hours_production' => 10.0,
            'hours_tpm' => 0,
            'hours_project' => 0,
            'hours_others' => 0,
        ]);

    return $today->toDateString();
}

test('manager opens Early Warning from the sidebar, sees the open Merah badge and the item stays highlighted on the detail page', function () {
    PolicyThreshold::factory()->plantDefault()->create();

    $department = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create(['department_id' => $department->id, 'is_active' => true]);
    $today = earlyWarningBrowserMerahToday($section);

    User::factory()->manager($department->id)->create([
        'email' => 'manager.early-warning@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'manager.early-warning@factory.com')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/dashboard')
        ->assertVisible('[data-test="nav-early-warning"]')
        ->assertSeeIn('[data-test="nav-early-warning-badge"]', '1')
        ->click('[data-test="nav-early-warning"]')
        ->assertPathIs('/early-warning')
        ->assertPresent('[data-test="early-warning-index"]')
        ->assertAttribute('[data-test="nav-early-warning"]', 'data-active', 'true')
        ->navigate("/early-warning/{$section->id}/{$today}")
        ->assertPresent('[data-test="early-warning-show"]')
        ->assertAttribute('[data-test="nav-early-warning"]', 'data-active', 'true')
        ->assertNoJavaScriptErrors();
});

test('the badge turns into a dot when the sidebar is collapsed', function () {
    PolicyThreshold::factory()->plantDefault()->create();

    $department = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create(['department_id' => $department->id, 'is_active' => true]);
    earlyWarningBrowserMerahToday($section);

    User::factory()->manager($department->id)->create([
        'email' => 'manager.early-warning.dot@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'manager.early-warning.dot@factory.com')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/dashboard')
        ->assertPresent('[data-test="nav-early-warning-badge"]')
        ->click('[data-slot="sidebar-trigger"]')
        ->assertVisible('[data-test="nav-early-warning-dot"]')
        ->assertMissing('[data-test="nav-early-warning-badge"]')
        ->assertNoJavaScriptErrors();
});

test('the menu shows no badge or dot when the department has no open Merah alert', function () {
    PolicyThreshold::factory()->plantDefault()->create();

    $department = Department::factory()->create(['is_active' => true]);

    User::factory()->manager($department->id)->create([
        'email' => 'manager.early-warning.quiet@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'manager.early-warning.quiet@factory.com')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/dashboard')
        ->assertVisible('[data-test="nav-early-warning"]')
        ->assertNotPresent('[data-test="nav-early-warning-badge"]')
        ->assertNotPresent('[data-test="nav-early-warning-dot"]');
});

test('operators do not see the Early Warning menu and are forbidden on the route', function () {
    $department = Department::factory()->create(['is_active' => true]);

    User::factory()->user()->create([
        'department_id' => $department->id,
        'email' => 'operator.early-warning@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'operator.early-warning@factory.com')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/my/dashboard')
        ->assertMissing('[data-test="nav-early-warning"]')
        ->navigate('/early-warning')
        ->assertSee('Access Restricted')
        ->assertMissing('[data-test="early-warning-index"]');
});
