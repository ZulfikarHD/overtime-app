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
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Today = Wed 14 Oct 2026. A 310 h October budget paces 10 h/day, so 10 h approved = 100 % (Merah)
 * and 8 h = 80 % (Kuning) with the default 70 / 90 limits.
 */

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-14 10:00:00', 'Asia/Jakarta'));

    PolicyThreshold::factory()->plantDefault()->create();

    $this->department = Department::factory()->create();
    $this->otherDepartment = Department::factory()->create();
    $this->section = Section::factory()->create(['department_id' => $this->department->id, 'name' => 'Assembly']);
    $this->otherSection = Section::factory()->create(['department_id' => $this->otherDepartment->id, 'name' => 'Welding']);
});

function inboxAlertOn(Section $section, string $date, float $hours): void
{
    OvertimeBudget::query()->firstOrCreate(
        ['section_id' => $section->id, 'fiscal_year' => 2026, 'fiscal_month' => 10],
        OvertimeBudget::factory()->forSection($section)->make(['fiscal_year' => 2026, 'fiscal_month' => 10, 'planned_hours' => 310.0])->getAttributes(),
    );

    $submission = OvertimeSubmission::factory()->create([
        'department_id' => $section->department_id,
        'section_id' => $section->id,
        'operational_date' => $date,
        'submission_date' => $date,
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
}

test('the inbox defers alerts and summary and defaults to the last 7 days', function () {
    inboxAlertOn($this->section, '2026-10-14', 10.0);
    inboxAlertOn($this->section, '2026-10-01', 10.0);
    $manager = User::factory()->manager($this->department->id)->create();

    $this->actingAs($manager)
        ->get(route('early-warning.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('early-warning/Index')
            ->where('filters.date_from', '2026-10-08')
            ->where('filters.date_to', '2026-10-14')
            ->where('filters.zone', 'all')
            ->where('filters.status', 'all')
            ->missing('alerts')
            ->missing('summary')
            ->loadDeferredProps('inbox', fn (Assert $reloaded) => $reloaded
                ->where('summary.total', 1)
                ->where('alerts.pagination.total', 1)
                ->where('alerts.data.0.alert_date', '2026-10-14')
                ->where('alerts.data.0.section_name', 'Assembly')
                ->where('alerts.data.0.zone', 'red')
                ->where('alerts.data.0.streak_days', 1)
                ->where('alerts.data.0.follow_up_status', 'none')));
});

test('empty filter values behave like the defaults', function () {
    inboxAlertOn($this->section, '2026-10-14', 10.0);
    $manager = User::factory()->manager($this->department->id)->create();

    $this->actingAs($manager)
        ->get('/early-warning?zone=&status=&section_id=&department_id=&date_from=&date_to=&page=')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.zone', 'all')
            ->where('filters.status', 'all')
            ->where('filters.page', 1)
            ->where('filters.date_to', '2026-10-14')
            ->loadDeferredProps('inbox', fn (Assert $reloaded) => $reloaded->where('alerts.pagination.total', 1)));
});

test('the date range filter reaches back to older alerts', function () {
    inboxAlertOn($this->section, '2026-10-01', 10.0);
    $manager = User::factory()->manager($this->department->id)->create();

    $this->actingAs($manager)
        ->get(route('early-warning.index', ['date_from' => '2026-10-01', 'date_to' => '2026-10-03']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.date_from', '2026-10-01')
            ->loadDeferredProps('inbox', fn (Assert $reloaded) => $reloaded
                ->where('alerts.pagination.total', 1)
                ->where('alerts.data.0.alert_date', '2026-10-01')));
});

test('an admin sees alerts of every department and can narrow them down, a manager never leaves their own', function () {
    inboxAlertOn($this->section, '2026-10-14', 10.0);
    inboxAlertOn($this->otherSection, '2026-10-14', 10.0);

    $admin = User::factory()->admin()->create();
    $manager = User::factory()->manager($this->department->id)->create();

    $this->actingAs($admin)
        ->get(route('early-warning.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('departments', fn ($departments) => collect($departments)->pluck('id')->contains($this->department->id)
                && collect($departments)->pluck('id')->contains($this->otherDepartment->id))
            ->where('sections', [])
            ->loadDeferredProps('inbox', fn (Assert $reloaded) => $reloaded->where('alerts.pagination.total', 2)));

    $this->actingAs($admin)
        ->get(route('early-warning.index', ['department_id' => $this->otherDepartment->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('sections', 1)
            ->where('sections.0.id', $this->otherSection->id)
            ->loadDeferredProps('inbox', fn (Assert $reloaded) => $reloaded
                ->where('alerts.pagination.total', 1)
                ->where('alerts.data.0.section_name', 'Welding')));

    $this->actingAs($manager)
        ->get(route('early-warning.index', ['department_id' => $this->otherDepartment->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.department_id', $this->department->id)
            ->has('departments', 1)
            ->where('departments.0.id', $this->department->id)
            ->has('sections', 1)
            ->loadDeferredProps('inbox', fn (Assert $reloaded) => $reloaded
                ->where('alerts.pagination.total', 1)
                ->where('alerts.data.0.section_name', 'Assembly')));
});

test('a section filter narrows the list and a section of another department is dropped', function () {
    $secondSection = Section::factory()->create(['department_id' => $this->department->id, 'name' => 'Painting']);
    inboxAlertOn($this->section, '2026-10-14', 10.0);
    inboxAlertOn($secondSection, '2026-10-14', 10.0);
    inboxAlertOn($this->otherSection, '2026-10-14', 10.0);
    $manager = User::factory()->manager($this->department->id)->create();

    $this->actingAs($manager)
        ->get(route('early-warning.index', ['section_id' => $secondSection->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.section_id', $secondSection->id)
            ->loadDeferredProps('inbox', fn (Assert $reloaded) => $reloaded
                ->where('alerts.pagination.total', 1)
                ->where('alerts.data.0.section_name', 'Painting')));

    $this->actingAs($manager)
        ->get(route('early-warning.index', ['section_id' => $this->otherSection->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.section_id', null)
            ->loadDeferredProps('inbox', fn (Assert $reloaded) => $reloaded
                ->where('alerts.pagination.total', 2)));
});

test('zone and follow-up status filters narrow the list while the summary keeps the whole period', function () {
    $kuning = Section::factory()->create(['department_id' => $this->department->id, 'name' => 'Kuning draft']);
    $handled = Section::factory()->create(['department_id' => $this->department->id, 'name' => 'Merah sent']);

    inboxAlertOn($this->section, '2026-10-14', 10.0);
    inboxAlertOn($kuning, '2026-10-14', 8.0);
    inboxAlertOn($handled, '2026-10-14', 10.0);
    AlertFollowUp::factory()->forAlert($kuning, '2026-10-14')->create();
    AlertFollowUp::factory()->forAlert($handled, '2026-10-14')->sent()->create();

    $manager = User::factory()->manager($this->department->id)->create();

    $this->actingAs($manager)
        ->get(route('early-warning.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('inbox', fn (Assert $reloaded) => $reloaded
                ->where('summary', ['total' => 3, 'red' => 2, 'yellow' => 1, 'without_follow_up' => 1])
                ->where('alerts.pagination.total', 3)
                ->where('alerts.data.0.zone', 'red')
                ->where('alerts.data.2.zone', 'yellow')));

    $this->actingAs($manager)
        ->get(route('early-warning.index', ['zone' => 'yellow']))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('inbox', fn (Assert $reloaded) => $reloaded
                ->where('summary.total', 3)
                ->where('alerts.pagination.total', 1)
                ->where('alerts.data.0.section_name', 'Kuning draft')
                ->where('alerts.data.0.follow_up_status', 'draft')));

    $this->actingAs($manager)
        ->get(route('early-warning.index', ['status' => 'none']))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('inbox', fn (Assert $reloaded) => $reloaded
                ->where('alerts.pagination.total', 1)
                ->where('alerts.data.0.section_name', 'Assembly')));

    $this->actingAs($manager)
        ->get(route('early-warning.index', ['status' => 'sent', 'zone' => 'red']))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('inbox', fn (Assert $reloaded) => $reloaded
                ->where('alerts.pagination.total', 1)
                ->where('alerts.data.0.section_name', 'Merah sent')
                ->where('alerts.data.0.follow_up_status', 'sent')));

    $this->actingAs($manager)
        ->get(route('early-warning.index', ['status' => 'sent', 'zone' => 'yellow']))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('inbox', fn (Assert $reloaded) => $reloaded
                ->where('alerts.pagination.total', 0)
                ->where('alerts.pagination.from', null)
                ->where('alerts.data', [])));
});

test('more than 25 alerts are paginated and an out-of-range page falls back to the last page', function () {
    foreach (range(1, 27) as $number) {
        $section = Section::factory()->create(['department_id' => $this->department->id, 'name' => sprintf('Line %02d', $number)]);
        inboxAlertOn($section, '2026-10-14', 10.0);
    }

    $manager = User::factory()->manager($this->department->id)->create();

    $this->actingAs($manager)
        ->get(route('early-warning.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('inbox', fn (Assert $reloaded) => $reloaded
                ->has('alerts.data', 25)
                ->where('alerts.pagination', [
                    'current_page' => 1, 'last_page' => 2, 'per_page' => 25, 'total' => 27, 'from' => 1, 'to' => 25,
                ])));

    foreach ([2, 99] as $requestedPage) {
        $this->actingAs($manager)
            ->get(route('early-warning.index', ['page' => $requestedPage]))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps('inbox', fn (Assert $reloaded) => $reloaded
                    ->has('alerts.data', 2)
                    ->where('alerts.pagination.current_page', 2)
                    ->where('alerts.pagination.from', 26)
                    ->where('alerts.pagination.to', 27)));
    }
});

test('invalid filters go back to the default inbox with an error', function (array $query) {
    $manager = User::factory()->manager($this->department->id)->create();

    $this->actingAs($manager)
        ->get(route('early-warning.index', $query))
        ->assertRedirect(route('early-warning.index'))
        ->assertSessionHas('error');
})->with([
    'unknown zone' => [['zone' => 'green']],
    'unknown status' => [['status' => 'approved']],
    'malformed date' => [['date_from' => '14-10-2026']],
    'impossible date' => [['date_to' => '2026-02-31']],
    'reversed range' => [['date_from' => '2026-10-10', 'date_to' => '2026-10-01']],
    'range too long' => [['date_from' => '2026-01-01', 'date_to' => '2026-10-14']],
    'non numeric section' => [['section_id' => 'abc']],
    'page zero' => [['page' => 0]],
]);

test('the longest accepted date range is 93 days including both ends', function () {
    $manager = User::factory()->manager($this->department->id)->create();

    $this->actingAs($manager)
        ->get(route('early-warning.index', ['date_from' => '2026-07-14', 'date_to' => '2026-10-14']))
        ->assertOk();

    $this->actingAs($manager)
        ->get(route('early-warning.index', ['date_from' => '2026-07-13', 'date_to' => '2026-10-14']))
        ->assertRedirect(route('early-warning.index'));
});

test('operators and guests cannot open the inbox with filters', function () {
    $operator = User::factory()->user()->create(['department_id' => $this->department->id]);

    $this->actingAs($operator)
        ->get(route('early-warning.index', ['zone' => 'red']))
        ->assertForbidden();

    auth()->logout();

    $this->get(route('early-warning.index', ['zone' => 'red']))->assertRedirect(route('login'));
});

test('a team leader is locked to their own department', function () {
    inboxAlertOn($this->section, '2026-10-14', 10.0);
    inboxAlertOn($this->otherSection, '2026-10-14', 10.0);
    $leader = User::factory()->teamLeader($this->section->id, $this->department->id)->create();

    $this->actingAs($leader)
        ->get(route('early-warning.index', ['department_id' => $this->otherDepartment->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.department_id', $this->department->id)
            ->loadDeferredProps('inbox', fn (Assert $reloaded) => $reloaded
                ->where('alerts.pagination.total', 1)
                ->where('alerts.data.0.department_id', $this->department->id)));
});

test('a manager without a department sees an empty inbox', function () {
    inboxAlertOn($this->section, '2026-10-14', 10.0);
    $manager = User::factory()->manager(null)->create();

    $this->actingAs($manager)
        ->get(route('early-warning.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('departments', [])
            ->where('sections', [])
            ->loadDeferredProps('inbox', fn (Assert $reloaded) => $reloaded
                ->where('summary.total', 0)
                ->where('alerts.data', [])));
});
