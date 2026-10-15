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
 * Today's Merah alert for the section: a 10 h/day pacing budget with 10 h approved = 100 %.
 */
function entryLinkSeedMerahToday(Section $section): void
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
}

function entryLinkLogin(string $email): mixed
{
    return visit('/login')
        ->fill('email', $email)
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/dashboard');
}

beforeEach(function () {
    PolicyThreshold::factory()->plantDefault()->create();

    $this->department = Department::factory()->create(['is_active' => true, 'name' => 'Link Dept A']);
    $this->otherDepartment = Department::factory()->create(['is_active' => true, 'name' => 'Link Dept B']);

    $this->merahSection = Section::factory()->create(['department_id' => $this->department->id, 'name' => 'Assembly Link', 'is_active' => true]);
    $this->calmSection = Section::factory()->create(['department_id' => $this->department->id, 'name' => 'Painting Tenang Link', 'is_active' => true]);
    $this->otherSection = Section::factory()->create(['department_id' => $this->otherDepartment->id, 'name' => 'Recruitment Link', 'is_active' => true]);
});

test('the chart link opens the inbox with the dashboard department, section and month-to-date range', function () {
    entryLinkSeedMerahToday($this->merahSection);
    entryLinkSeedMerahToday($this->otherSection);
    User::factory()->admin()->create(['email' => 'admin.entrylink@factory.com', 'password' => 'password']);

    $today = Carbon::now('Asia/Jakarta');

    entryLinkLogin('admin.entrylink@factory.com')
        ->navigate("/dashboard?department_id={$this->department->id}&section_id={$this->merahSection->id}&tab=distribution")
        ->assertPresent('[data-test="early-warning-open-link"]')
        ->click('[data-test="early-warning-open-link"]')
        ->assertPathIs('/early-warning')
        ->assertQueryStringHas('department_id', (string) $this->department->id)
        ->assertQueryStringHas('section_id', (string) $this->merahSection->id)
        ->assertQueryStringHas('date_from', $today->copy()->startOfMonth()->toDateString())
        ->assertQueryStringHas('date_to', $today->toDateString())
        ->assertSee('Assembly Link')
        ->assertDontSee('Recruitment Link')
        ->assertNoJavaScriptErrors();
});

test('the link stays visible when the chart is Hijau and the inbox then shows its empty state', function () {
    User::factory()->manager($this->department->id)->create(['email' => 'manager.entrylink@factory.com', 'password' => 'password']);

    entryLinkLogin('manager.entrylink@factory.com')
        ->click('[data-test="tab-distribution"]')
        ->assertPresent('[data-test="early-warning-open-link"]')
        ->click('[data-test="early-warning-open-link"]')
        ->assertPathIs('/early-warning')
        ->assertSee('No alerts in this period')
        ->assertNoJavaScriptErrors();
});

test('a team leader sees the link and lands on the inbox of their own department', function () {
    entryLinkSeedMerahToday($this->merahSection);
    entryLinkSeedMerahToday($this->otherSection);
    User::factory()->teamLeader($this->merahSection->id, $this->department->id)->create(['email' => 'leader.entrylink@factory.com', 'password' => 'password']);

    entryLinkLogin('leader.entrylink@factory.com')
        ->click('[data-test="tab-distribution"]')
        ->click('[data-test="early-warning-open-link"]')
        ->assertPathIs('/early-warning')
        ->assertSee('Assembly Link')
        ->assertDontSee('Recruitment Link')
        ->assertNoJavaScriptErrors();
});
