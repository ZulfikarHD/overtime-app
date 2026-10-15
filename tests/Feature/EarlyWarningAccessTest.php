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
 * Today = Wed 14 Oct 2026. The index is month-to-date and a 310 h October budget expects 10 h per calendar day.
 * badgeSectionWithHours() takes the index of the day in tenths: 10 = 100 % (Merah), 8 = 80 % (Kuning) with the
 * default 70 / 90 limits. It books the cumulative hours that give that index on the date (hours x day of month).
 */

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-14 10:00:00', 'Asia/Jakarta'));

    PolicyThreshold::factory()->plantDefault()->create();

    $this->department = Department::factory()->create();
    $this->otherDepartment = Department::factory()->create();
    $this->section = Section::factory()->create(['department_id' => $this->department->id]);
    $this->otherSection = Section::factory()->create(['department_id' => $this->otherDepartment->id]);
});

function badgeSectionWithHours(Section $section, string $date, float $hours): void
{
    $hours *= (int) substr($date, 8, 2);

    OvertimeBudget::factory()->forSection($section)->create([
        'fiscal_year' => 2026,
        'fiscal_month' => 10,
        'planned_hours' => 310.0,
    ]);

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

test('admin, manager and team leader open the alert inbox', function (string $role) {
    $user = match ($role) {
        'admin' => User::factory()->admin()->create(),
        'manager' => User::factory()->manager($this->department->id)->create(),
        'team_leader' => User::factory()->teamLeader($this->section->id, $this->department->id)->create(),
    };

    $this->actingAs($user)
        ->get(route('early-warning.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('early-warning/Index'));
})->with(['admin', 'manager', 'team_leader']);

test('operators get 403 on both routes and guests are sent to login', function () {
    $operator = User::factory()->user()->create(['department_id' => $this->department->id]);

    $this->actingAs($operator)->get(route('early-warning.index'))->assertForbidden();
    $this->actingAs($operator)
        ->get(route('early-warning.show', [$this->section, '2026-10-13']))
        ->assertForbidden();

    auth()->logout();

    $this->get(route('early-warning.index'))->assertRedirect(route('login'));
});

test('a manager opens an alert detail of their own department', function () {
    $manager = User::factory()->manager($this->department->id)->create();

    $this->actingAs($manager)
        ->get(route('early-warning.show', [$this->section, '2026-10-13']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('early-warning/Show')
            ->where('section.id', $this->section->id)
            ->where('section.department_id', $this->department->id)
            ->where('date', '2026-10-13'));
});

test('a non-admin requesting a section of another department gets 403, an admin does not', function () {
    $manager = User::factory()->manager($this->department->id)->create();
    $leader = User::factory()->teamLeader($this->section->id, $this->department->id)->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($manager)
        ->get(route('early-warning.show', [$this->otherSection, '2026-10-13']))
        ->assertForbidden();
    $this->actingAs($leader)
        ->get(route('early-warning.show', [$this->otherSection, '2026-10-13']))
        ->assertForbidden();
    $this->actingAs($admin)
        ->get(route('early-warning.show', [$this->otherSection, '2026-10-13']))
        ->assertOk();
});

test('a manager without a department cannot open any section', function () {
    $manager = User::factory()->manager(null)->create();

    $this->actingAs($manager)
        ->get(route('early-warning.show', [$this->section, '2026-10-13']))
        ->assertForbidden();
});

test('an unknown section is a 404 and an invalid date goes back to the inbox with an error', function () {
    $manager = User::factory()->manager($this->department->id)->create();

    $this->actingAs($manager)->get('/early-warning/999999/2026-10-13')->assertNotFound();

    foreach (['not-a-date', '2026-02-31', '13-10-2026'] as $invalidDate) {
        $this->actingAs($manager)
            ->get(route('early-warning.show', [$this->section, $invalidDate]))
            ->assertRedirect(route('early-warning.index'))
            ->assertSessionHas('error');
    }
});

test('an invalid date does not reveal another department section', function () {
    $manager = User::factory()->manager($this->department->id)->create();

    $this->actingAs($manager)
        ->get(route('early-warning.show', [$this->otherSection, 'not-a-date']))
        ->assertForbidden();
});

test('the sidebar badge counts open Merah alerts of the current month within the user scope', function () {
    // Own department: Merah (counts), Kuning (does not), Merah with a draft (counts), Merah already sent (does not).
    badgeSectionWithHours($this->section, '2026-10-14', 10.0);
    badgeSectionWithHours(Section::factory()->create(['department_id' => $this->department->id]), '2026-10-14', 8.0);

    $draftSection = Section::factory()->create(['department_id' => $this->department->id]);
    badgeSectionWithHours($draftSection, '2026-10-14', 10.0);
    AlertFollowUp::factory()->forAlert($draftSection, '2026-10-14')->create();

    $sentSection = Section::factory()->create(['department_id' => $this->department->id]);
    badgeSectionWithHours($sentSection, '2026-10-14', 10.0);
    AlertFollowUp::factory()->forAlert($sentSection, '2026-10-14')->sent()->create();

    // Other department: Merah, only visible to admins.
    badgeSectionWithHours($this->otherSection, '2026-10-14', 10.0);

    $manager = User::factory()->manager($this->department->id)->create();
    $admin = User::factory()->admin()->create();
    $operator = User::factory()->user()->create(['department_id' => $this->department->id]);

    $this->actingAs($manager)->get(route('early-warning.index'))
        ->assertInertia(fn (Assert $page) => $page->where('early_warning_open_count', 2));

    $this->actingAs($admin)->get(route('early-warning.index'))
        ->assertInertia(fn (Assert $page) => $page->where('early_warning_open_count', 3));

    $this->actingAs($operator)->get(route('my.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('early_warning_open_count', 0));
});

test('the sidebar badge is zero when nothing is Merah', function () {
    badgeSectionWithHours($this->section, '2026-10-13', 8.0);

    $manager = User::factory()->manager($this->department->id)->create();

    $this->actingAs($manager)->get(route('early-warning.index'))
        ->assertInertia(fn (Assert $page) => $page->where('early_warning_open_count', 0));
});
