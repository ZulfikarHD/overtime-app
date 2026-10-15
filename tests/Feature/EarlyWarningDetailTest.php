<?php

use App\Models\AlertFollowUp;
use App\Models\Department;
use App\Models\Employee;
use App\Models\MlTrainingData;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\PolicyThreshold;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Today = Wed 14 Oct 2026. The index is month-to-date and a 310 h October budget expects 10 h per calendar day,
 * so 100 % (Merah) is 10 h x the day of the month and 80 % (Kuning) is 8 h x the day, with the default 70 / 90 limits.
 */

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-14 10:00:00', 'Asia/Jakarta'));

    PolicyThreshold::factory()->plantDefault()->create();

    $this->department = Department::factory()->create();
    $this->section = Section::factory()->create(['department_id' => $this->department->id]);
    $this->manager = User::factory()->manager($this->department->id)->create();
});

/**
 * Books approved hours on a date. By default `$hours` is the index of the day in tenths (10 = 100 %, 8 = 80 %): the
 * cumulative hours that give exactly that month-to-date index on the date (hours x day of month, 10 h expected per
 * day). With `$cumulative = false` the hours are booked as they are.
 */
function detailSeedDay(Section $section, string $date, float $hours, bool $cumulative = true): void
{
    if ($cumulative) {
        $hours *= (int) substr($date, 8, 2);
    }

    if (! OvertimeBudget::query()->where('section_id', $section->id)->exists()) {
        OvertimeBudget::factory()->forSection($section)->create([
            'fiscal_year' => 2026,
            'fiscal_month' => 10,
            'planned_hours' => 310.0,
        ]);
    }

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

test('the detail page exposes the alert, its streak, the 7 working day series and the zone limits', function () {
    // 96 h by 12 Oct = 80 % Kuning, +24 h = 120 h by 13 Oct = 92.3 % Merah.
    detailSeedDay($this->section, '2026-10-12', 8.0);
    detailSeedDay($this->section, '2026-10-13', 24.0, cumulative: false);

    $this->actingAs($this->manager)
        ->get(route('early-warning.show', [$this->section, '2026-10-13']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('early-warning/Show')
            ->where('alert.section_id', $this->section->id)
            ->where('alert.zone', 'red')
            ->where('alert.streak_days', 2)
            ->where('alert.follow_up_status', 'none')
            ->where('alert.index_pct', fn ($value) => abs($value - 92.3) < 0.01)
            ->has('series', 7)
            ->where('series.6.date', '2026-10-13')
            ->where('series.6.zone', 'red')
            ->where('series.5.date', '2026-10-12')
            ->where('series.5.zone', 'yellow')
            ->where('limits.green_max_pct', 70)
            ->where('limits.yellow_max_pct', 90)
            ->where('followUps', []));
});

test('the limits follow the department override instead of the plant default', function () {
    PolicyThreshold::factory()->forDepartment($this->department)->create([
        'green_max_pct' => 85.0,
        'yellow_max_pct' => 100.0,
    ]);
    detailSeedDay($this->section, '2026-10-13', 10.0);

    $this->actingAs($this->manager)
        ->get(route('early-warning.show', [$this->section, '2026-10-13']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('limits.green_max_pct', 85)
            ->where('limits.yellow_max_pct', 100)
            ->where('alert.zone', 'yellow'));
});

test('a date that is not an alert still opens with alert null and the series for context', function () {
    detailSeedDay($this->section, '2026-10-13', 5.0);

    $this->actingAs($this->manager)
        ->get(route('early-warning.show', [$this->section, '2026-10-13']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('early-warning/Show')
            ->where('alert', null)
            ->where('series.6.zone', 'green')
            ->has('series', 7));
});

test('a section without a budget and a future date open without an alert and without breaking', function () {
    $this->actingAs($this->manager)
        ->get(route('early-warning.show', [$this->section, '2026-10-13']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('alert', null)->where('series', []));

    detailSeedDay($this->section, '2026-10-13', 10.0);

    $this->actingAs($this->manager)
        ->get(route('early-warning.show', [$this->section, '2026-10-20']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('alert', null));
});

test('vitals are null without monthly data, so the page shows N/A and never a made-up number', function () {
    detailSeedDay($this->section, '2026-10-13', 10.0);

    $this->actingAs($this->manager)
        ->get(route('early-warning.show', [$this->section, '2026-10-13']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('vitals.volume_change_pct', null)
            ->where('vitals.manpower_change_pct', null)
            ->where('vitals.volume_period_label', null)
            ->where('vitals.manpower_period_label', null));
});

test('the detail page exposes the real vitals of the alert month against the previous month', function () {
    detailSeedDay($this->section, '2026-10-13', 10.0);
    MlTrainingData::factory()->create(['year' => 2026, 'month' => 9, 'production_volume' => 2000, 'man_power' => 800]);
    MlTrainingData::factory()->create(['year' => 2026, 'month' => 10, 'production_volume' => 2400, 'man_power' => 800]);

    $this->actingAs($this->manager)
        ->get(route('early-warning.show', [$this->section, '2026-10-13']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('vitals.volume_change_pct', 20)
            ->where('vitals.volume_period_label', 'vs September 2026')
            ->where('vitals.manpower_change_pct', 0)
            ->where('vitals.manpower_period_label', 'vs September 2026 (plant-wide)'));
});

test('only the follow-ups of this section and date are listed, newest first, with the sender of a sent one', function () {
    detailSeedDay($this->section, '2026-10-13', 10.0);

    $sender = User::factory()->manager($this->department->id)->create(['name' => 'Budi Pengirim']);
    $older = AlertFollowUp::factory()->forAlert($this->section, '2026-10-13')->sent()->create([
        'created_by' => $sender->id,
        'sent_by' => $sender->id,
        'what_text' => 'Older sent follow-up',
    ]);
    $newer = AlertFollowUp::factory()->forAlert($this->section, '2026-10-13')->create(['what_text' => 'Newer draft']);

    AlertFollowUp::factory()->forAlert($this->section, '2026-10-12')->create(['what_text' => 'Other date']);
    AlertFollowUp::factory()
        ->forAlert(Section::factory()->create(['department_id' => $this->department->id]), '2026-10-13')
        ->create(['what_text' => 'Other section']);

    $this->actingAs($this->manager)
        ->get(route('early-warning.show', [$this->section, '2026-10-13']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('followUps', 2)
            ->where('followUps.0.id', $newer->id)
            ->where('followUps.0.status', 'draft')
            ->where('followUps.0.sent_by_name', null)
            ->where('followUps.1.id', $older->id)
            ->where('followUps.1.status', 'sent')
            ->where('followUps.1.sent_by_name', 'Budi Pengirim')
            ->where('alert.follow_up_status', 'draft'));
});

test('the back link restores the inbox filters from a same-origin referer', function () {
    detailSeedDay($this->section, '2026-10-13', 10.0);

    $this->actingAs($this->manager)
        ->withHeader('referer', url('/early-warning?zone=red&status=none'))
        ->get(route('early-warning.show', [$this->section, '2026-10-13']))
        ->assertInertia(fn (Assert $page) => $page->where('backUrl', '/early-warning?zone=red&status=none'));
});

test('a foreign or non-inbox referer is never used as the back link', function (string $referer) {
    detailSeedDay($this->section, '2026-10-13', 10.0);

    $this->actingAs($this->manager)
        ->withHeader('referer', $referer)
        ->get(route('early-warning.show', [$this->section, '2026-10-13']))
        ->assertInertia(fn (Assert $page) => $page->where('backUrl', '/early-warning'));
})->with([
    'foreign host' => 'https://evil.example.com/early-warning?zone=red',
    'other page' => 'http://localhost/dashboard?zone=red',
    'malformed' => 'http:///???',
]);

test('after a reload the back link falls back to the last inbox filters kept in the session', function () {
    detailSeedDay($this->section, '2026-10-13', 10.0);

    $this->actingAs($this->manager)->get('/early-warning?zone=yellow&page=1')->assertOk();

    // Symfony normalizes the query string (keys sorted), which is equivalent for the inbox.
    $this->get(route('early-warning.show', [$this->section, '2026-10-13']))
        ->assertInertia(fn (Assert $page) => $page->where('backUrl', '/early-warning?page=1&zone=yellow'));
});

test('the back link is the plain inbox when the user never visited it', function () {
    detailSeedDay($this->section, '2026-10-13', 10.0);

    $this->actingAs($this->manager)
        ->get(route('early-warning.show', [$this->section, '2026-10-13']))
        ->assertInertia(fn (Assert $page) => $page->where('backUrl', '/early-warning'));
});

test('another department never receives the detail or its follow-ups', function () {
    $otherSection = Section::factory()->create(['department_id' => Department::factory()->create()->id]);
    detailSeedDay($otherSection, '2026-10-13', 10.0);
    AlertFollowUp::factory()->forAlert($otherSection, '2026-10-13')->create(['what_text' => 'Secret follow-up']);

    $this->actingAs($this->manager)
        ->get(route('early-warning.show', [$otherSection, '2026-10-13']))
        ->assertForbidden();
});
