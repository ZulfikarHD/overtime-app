<?php

use App\Models\AlertFollowUp;
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
 * Today's approved hours of a section against a 10 h/day pacing budget:
 * 10 h = 100 % (Merah), 8 h = 80 % (Kuning), 5 h = 50 % (Hijau) with the default 70 / 90 limits.
 */
function detailBrowserSeedToday(Section $section, float $hours): string
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
            'hours_production' => $hours,
            'hours_tpm' => 0,
            'hours_project' => 0,
            'hours_others' => 0,
        ]);

    return $today->toDateString();
}

function detailBrowserLogin(string $email): mixed
{
    return visit('/login')
        ->fill('email', $email)
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/dashboard');
}

beforeEach(function () {
    PolicyThreshold::factory()->plantDefault()->create();

    $this->department = Department::factory()->create(['is_active' => true, 'name' => 'PCD Detail Dept']);
    $this->merahSection = Section::factory()->create(['department_id' => $this->department->id, 'name' => 'Assembly Detail', 'is_active' => true]);
    $this->hijauSection = Section::factory()->create(['department_id' => $this->department->id, 'name' => 'Painting Tenang', 'is_active' => true]);
});

test('opening an alert from the inbox shows the header, burn bar, vitals, chart and follow-up status', function () {
    $today = detailBrowserSeedToday($this->merahSection, 10.0);
    User::factory()->manager($this->department->id)->create(['email' => 'manager.detail@factory.com', 'password' => 'password']);

    detailBrowserLogin('manager.detail@factory.com')
        ->navigate('/early-warning')
        ->click('[data-test="alert-row"][data-section="Assembly Detail"] [data-test="alert-action"]')
        ->assertPathIs("/early-warning/{$this->merahSection->id}/{$today}")
        ->assertSeeIn('[data-test="alert-header-title"]', 'Daily Index — Assembly Detail')
        ->assertAttribute('[data-test="alert-header-card"]', 'data-zone', 'red')
        ->assertAttribute('[data-test="alert-zone-badge"]', 'data-zone', 'red')
        ->assertSeeIn('[data-test="alert-header-index"]', '100.0%')
        ->assertSeeIn('[data-test="alert-header-streak"]', 'consecutive days')
        ->assertPresent('[data-test="alert-burn-bar-marker-green"]')
        ->assertPresent('[data-test="alert-burn-bar-marker-yellow"]')
        ->assertSeeIn('[data-test="alert-burn-bar-caption"]', 'Target 100%')
        ->assertSeeIn('[data-test="alert-vital-volume-value"]', 'N/A')
        ->assertSeeIn('[data-test="alert-vital-manpower-value"]', 'N/A')
        ->assertPresent('[data-test="alert-index-chart"] canvas')
        ->assertSeeIn('[data-test="alert-follow-up-summary"]', 'Not followed up yet')
        ->assertNotPresent('[data-test="alert-not-an-alert"]')
        ->assertNoJavaScriptErrors();
});

test('the back link returns to the inbox with the filters the user came from', function () {
    detailBrowserSeedToday($this->merahSection, 10.0);
    User::factory()->manager($this->department->id)->create(['email' => 'manager.back@factory.com', 'password' => 'password']);

    detailBrowserLogin('manager.back@factory.com')
        ->navigate('/early-warning?zone=red')
        ->click('[data-test="alert-row"][data-section="Assembly Detail"] [data-test="alert-action"]')
        ->assertPresent('[data-test="early-warning-show"]')
        ->click('[data-test="early-warning-back"]')
        ->assertPathIs('/early-warning')
        ->assertQueryStringHas('zone', 'red')
        ->assertAttribute('[data-test="filter-zone-red"]', 'aria-pressed', 'true')
        ->assertNoJavaScriptErrors();
});

test('a date that is no longer an alert shows the neutral banner instead of an alert header', function () {
    $today = detailBrowserSeedToday($this->hijauSection, 5.0);
    User::factory()->manager($this->department->id)->create(['email' => 'manager.calm@factory.com', 'password' => 'password']);

    detailBrowserLogin('manager.calm@factory.com')
        ->navigate("/early-warning/{$this->hijauSection->id}/{$today}")
        ->assertSeeIn('[data-test="alert-not-an-alert"]', 'No alert on this date')
        ->assertNotPresent('[data-test="alert-header-status"]')
        ->assertPresent('[data-test="alert-index-chart"] canvas')
        ->assertNoJavaScriptErrors();
});

test('a sent follow-up shows who sent it and the history can be switched between follow-ups', function () {
    $today = detailBrowserSeedToday($this->merahSection, 10.0);
    User::factory()->manager($this->department->id)->create(['email' => 'manager.history@factory.com', 'password' => 'password']);

    $first = AlertFollowUp::factory()->forAlert($this->merahSection, $today)->sent()->create(['what_text' => 'First sent follow-up']);
    $second = AlertFollowUp::factory()->forAlert($this->merahSection, $today)->create(['what_text' => 'Second draft follow-up']);

    detailBrowserLogin('manager.history@factory.com')
        ->navigate("/early-warning/{$this->merahSection->id}/{$today}")
        ->assertSeeIn('[data-test="alert-follow-up-what"]', 'Second draft follow-up')
        ->assertNotPresent('[data-test="alert-follow-up-sent-info"]')
        ->click("[data-test=\"alert-follow-up-chip-{$first->id}\"]")
        ->assertSeeIn('[data-test="alert-follow-up-what"]', 'First sent follow-up')
        ->assertSeeIn('[data-test="alert-follow-up-sent-info"]', '(read-only)')
        ->assertAttribute('[data-test="follow-up-status-pill"]', 'data-status', 'sent')
        ->click("[data-test=\"alert-follow-up-chip-{$second->id}\"]")
        ->assertSeeIn('[data-test="alert-follow-up-what"]', 'Second draft follow-up')
        ->assertNoJavaScriptErrors();
});

test('on a phone the detail page stacks its cards without a broken layout', function () {
    $today = detailBrowserSeedToday($this->merahSection, 10.0);
    User::factory()->manager($this->department->id)->create(['email' => 'manager.phone@factory.com', 'password' => 'password']);

    detailBrowserLogin('manager.phone@factory.com')
        ->resize(390, 844)
        ->navigate("/early-warning/{$this->merahSection->id}/{$today}")
        ->assertVisible('[data-test="alert-header-card"]')
        ->assertVisible('[data-test="alert-burn-bar"]')
        ->assertVisible('[data-test="alert-index-chart"]')
        ->assertNoJavaScriptErrors();
});
