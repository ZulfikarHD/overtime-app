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
 * 10 h = 100 % (Merah), 8 h = 80 % (Kuning) with the default 70 / 90 limits.
 */
function inboxBrowserAlertToday(Section $section, float $hours): string
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

beforeEach(function () {
    PolicyThreshold::factory()->plantDefault()->create();

    $this->department = Department::factory()->create(['is_active' => true, 'name' => 'PCD Inbox Dept']);
    $this->otherDepartment = Department::factory()->create(['is_active' => true, 'name' => 'HCM Inbox Dept']);

    $this->merahSection = Section::factory()->create(['department_id' => $this->department->id, 'name' => 'Assembly Merah', 'is_active' => true]);
    $this->kuningSection = Section::factory()->create(['department_id' => $this->department->id, 'name' => 'Painting Kuning', 'is_active' => true]);
    $this->sentSection = Section::factory()->create(['department_id' => $this->department->id, 'name' => 'Welding Terkirim', 'is_active' => true]);
    $this->otherSection = Section::factory()->create(['department_id' => $this->otherDepartment->id, 'name' => 'Recruitment Lain', 'is_active' => true]);
});

function inboxBrowserSeedAlerts(): string
{
    $today = inboxBrowserAlertToday(test()->merahSection, 10.0);
    inboxBrowserAlertToday(test()->kuningSection, 8.0);
    inboxBrowserAlertToday(test()->sentSection, 10.0);
    inboxBrowserAlertToday(test()->otherSection, 10.0);

    AlertFollowUp::factory()->forAlert(test()->kuningSection, $today)->create();
    AlertFollowUp::factory()->forAlert(test()->sentSection, $today)->sent()->create();

    return $today;
}

function inboxBrowserLogin(string $email): mixed
{
    return visit('/login')
        ->fill('email', $email)
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/dashboard');
}

test('an admin sees the alerts of every department together with the summary', function () {
    inboxBrowserSeedAlerts();
    User::factory()->admin()->create(['email' => 'admin.inbox@factory.com', 'password' => 'password']);

    inboxBrowserLogin('admin.inbox@factory.com')
        ->navigate('/early-warning')
        ->assertSeeIn('[data-test="summary-total"]', '4')
        ->assertSeeIn('[data-test="summary-red"]', '3')
        ->assertSeeIn('[data-test="summary-yellow"]', '1')
        ->assertSeeIn('[data-test="summary-without-follow-up"]', '2')
        ->assertSee('Assembly Merah')
        ->assertSee('Recruitment Lain')
        ->assertNoJavaScriptErrors();
});

test('a manager only sees the alerts of their own department and cannot change the department', function () {
    inboxBrowserSeedAlerts();
    User::factory()->manager($this->department->id)->create(['email' => 'manager.inbox@factory.com', 'password' => 'password']);

    inboxBrowserLogin('manager.inbox@factory.com')
        ->navigate('/early-warning')
        ->assertSeeIn('[data-test="summary-total"]', '3')
        ->assertSee('Assembly Merah')
        ->assertSee('Painting Kuning')
        ->assertDontSee('Recruitment Lain')
        ->assertAttribute('[data-test="filter-department"]', 'data-disabled', '')
        ->assertNoJavaScriptErrors();
});

test('filtering by zone and follow-up status updates the list and the URL, and an empty result shows the empty state', function () {
    inboxBrowserSeedAlerts();
    User::factory()->manager($this->department->id)->create(['email' => 'manager.filters@factory.com', 'password' => 'password']);

    inboxBrowserLogin('manager.filters@factory.com')
        ->navigate('/early-warning')
        ->assertSee('Assembly Merah')
        ->click('[data-test="filter-zone-yellow"]')
        ->assertQueryStringHas('zone', 'yellow')
        ->assertSee('Painting Kuning')
        ->assertDontSee('Assembly Merah')
        ->assertSeeIn('[data-test="summary-total"]', '3')
        ->click('[data-test="filter-status-sent"]')
        ->assertQueryStringHas('status', 'sent')
        ->assertSee('No alerts in this period')
        ->assertDontSee('Painting Kuning')
        ->click('[data-test="filter-reset"]')
        ->assertSee('Assembly Merah')
        ->assertSee('Painting Kuning')
        ->assertSee('Welding Terkirim')
        ->click('[data-test="filter-status-none"]')
        ->assertSee('Assembly Merah')
        ->assertDontSee('Welding Terkirim')
        ->assertNoJavaScriptErrors();
});

test('the filters are restored from the URL when the page is reloaded or shared', function () {
    inboxBrowserSeedAlerts();
    User::factory()->manager($this->department->id)->create(['email' => 'manager.url@factory.com', 'password' => 'password']);

    inboxBrowserLogin('manager.url@factory.com')
        ->navigate('/early-warning?zone=red&status=sent')
        ->assertSee('Welding Terkirim')
        ->assertDontSee('Assembly Merah')
        ->assertAttribute('[data-test="filter-zone-red"]', 'aria-pressed', 'true')
        ->assertAttribute('[data-test="filter-status-sent"]', 'aria-pressed', 'true')
        ->assertNoJavaScriptErrors();
});

test('an admin narrows the inbox to one department with the department filter', function () {
    inboxBrowserSeedAlerts();
    User::factory()->admin()->create(['email' => 'admin.dept@factory.com', 'password' => 'password']);

    inboxBrowserLogin('admin.dept@factory.com')
        ->navigate('/early-warning')
        ->assertSee('Recruitment Lain')
        ->click('[data-test="filter-department"]')
        ->click('[role="option"]:has-text("HCM Inbox Dept")')
        ->assertQueryStringHas('department_id', (string) $this->otherDepartment->id)
        ->assertSee('Recruitment Lain')
        ->assertDontSee('Assembly Merah')
        ->assertNoJavaScriptErrors();
});

test('opening an alert row leads to the alert detail page', function () {
    $today = inboxBrowserSeedAlerts();
    User::factory()->manager($this->department->id)->create(['email' => 'manager.open@factory.com', 'password' => 'password']);

    inboxBrowserLogin('manager.open@factory.com')
        ->navigate('/early-warning')
        ->click('[data-test="alert-row"][data-section="Assembly Merah"] [data-test="alert-action"]')
        ->assertPathIs("/early-warning/{$this->merahSection->id}/{$today}")
        ->assertPresent('[data-test="early-warning-show"]')
        ->assertNoJavaScriptErrors();
});

test('a department without alerts gets the positive empty state', function () {
    User::factory()->manager($this->otherDepartment->id)->create(['email' => 'manager.quiet@factory.com', 'password' => 'password']);

    inboxBrowserLogin('manager.quiet@factory.com')
        ->navigate('/early-warning')
        ->assertSee('No alerts in this period')
        ->assertSeeIn('[data-test="summary-total"]', '0')
        ->assertNotPresent('[data-test="alert-row"]')
        ->assertNoJavaScriptErrors();
});

test('an invalid date range is explained and never submitted', function () {
    inboxBrowserSeedAlerts();
    User::factory()->manager($this->department->id)->create(['email' => 'manager.range@factory.com', 'password' => 'password']);

    inboxBrowserLogin('manager.range@factory.com')
        ->navigate('/early-warning')
        ->fill('[data-test="filter-date-from"]', '2099-01-01')
        ->assertPresent('[data-test="filter-date-error"]')
        ->assertSee('The start date must not be after the end date.')
        ->assertSee('Assembly Merah')
        ->assertNoJavaScriptErrors();
});

test('more than 25 alerts are split over pages that can be browsed', function () {
    foreach (range(1, 26) as $number) {
        $section = Section::factory()->create([
            'department_id' => $this->department->id,
            'name' => sprintf('Paged Line %02d', $number),
            'is_active' => true,
        ]);
        inboxBrowserAlertToday($section, 10.0);
    }

    User::factory()->manager($this->department->id)->create(['email' => 'manager.paging@factory.com', 'password' => 'password']);

    inboxBrowserLogin('manager.paging@factory.com')
        ->navigate('/early-warning')
        ->assertSeeIn('[data-test="alert-pagination-range"]', '1–25 / 26')
        ->assertPresent('[data-test="alert-page-next"]')
        ->click('[data-test="alert-page-next"]')
        ->assertQueryStringHas('page', '2')
        ->assertSeeIn('[data-test="alert-pagination-range"]', '26–26 / 26')
        ->assertAttribute('[data-test="alert-page-next"]', 'disabled', '')
        ->click('[data-test="alert-page-prev"]')
        ->assertSeeIn('[data-test="alert-pagination-range"]', '1–25 / 26')
        ->assertNoJavaScriptErrors();
});

test('on a phone the alerts are stacked cards instead of a table', function () {
    inboxBrowserSeedAlerts();
    User::factory()->manager($this->department->id)->create(['email' => 'manager.mobile@factory.com', 'password' => 'password']);

    inboxBrowserLogin('manager.mobile@factory.com')
        ->resize(390, 844)
        ->navigate('/early-warning')
        ->assertVisible('[data-test="alert-card"][data-section="Assembly Merah"]')
        ->assertMissing('[data-test="alert-table"]')
        ->assertNoJavaScriptErrors();
});
