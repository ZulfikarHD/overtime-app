<?php

use App\Enums\AlertFollowUpStatus;
use App\Mail\AlertFollowUpMail;
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
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Today = Wed 14 Oct 2026. A 310 h October budget paces 10 h/day: 10 h approved = 100 % (Merah),
 * 8 h = 80 % (Kuning), 5 h = 50 % (Hijau) with the default 70 / 90 limits.
 * Seeded days: Mon 12 Oct 8 h (Kuning) and Tue 13 Oct 10 h (Merah) => the 13th is an alert with a 2 day streak.
 */

beforeEach(function () {
    app()->setLocale('en');
    $this->travelTo(Carbon::parse('2026-10-14 10:00:00', 'Asia/Jakarta'));

    PolicyThreshold::factory()->plantDefault()->create();

    $this->dept = Department::factory()->create(['is_active' => true]);
    $this->picDept = Department::factory()->create(['is_active' => true]);
    $this->section = Section::factory()->create(['department_id' => $this->dept->id]);
    $this->manager = User::factory()->manager($this->dept->id)->create();

    followUpSeedDay($this->section, '2026-10-12', 8.0);
    followUpSeedDay($this->section, '2026-10-13', 10.0);
});

function followUpSeedDay(Section $section, string $date, float $hours): void
{
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

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function followUpPayload(Section $section, Department $picDept, array $overrides = []): array
{
    return array_merge([
        'section_id' => $section->id,
        'alert_date' => '2026-10-13',
        'what_text' => 'Index harian melewati batas Kuning',
        'why_text' => 'Volume produksi naik 20%, MP tidak bertambah',
        'when_date' => '2026-10-15',
        'where_location' => 'Line Assembly',
        'who_department_id' => $picDept->id,
        'how_text' => 'Tambah 5 orang shift malam selama 1 minggu',
        'how_much_cost_idr' => 7500000,
        'how_much_hours' => 120,
    ], $overrides);
}

test('manager saves a draft linked to the alert with a server-computed snapshot and no email', function () {
    Mail::fake();

    $this->actingAs($this->manager)
        ->post(route('early-warning.follow-ups.store'), followUpPayload($this->section, $this->picDept, [
            'why_text' => null,
            'how_text' => null,
            // Client-side values must be ignored.
            'zone' => 'green',
            'index_pct' => 1,
            'streak_days' => 99,
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $followUp = AlertFollowUp::query()->sole();

    expect($followUp->status)->toBe(AlertFollowUpStatus::Draft)
        ->and($followUp->department_id)->toBe($this->dept->id)
        ->and($followUp->section_id)->toBe($this->section->id)
        ->and($followUp->alert_date->toDateString())->toBe('2026-10-13')
        ->and($followUp->fiscal_year)->toBe(2026)
        ->and($followUp->fiscal_month)->toBe(10)
        ->and($followUp->zone->value)->toBe('red')
        ->and((float) $followUp->index_pct)->toBe(100.0)
        ->and($followUp->streak_days)->toBe(2)
        ->and($followUp->created_by)->toBe($this->manager->id)
        ->and($followUp->how_much_cost_idr)->toBe(7500000)
        ->and($followUp->sent_at)->toBeNull();

    Mail::assertNothingQueued();
});

test('the system suggestion is stored next to the user text so edits can be audited', function () {
    $this->actingAs($this->manager)
        ->post(route('early-warning.follow-ups.store'), followUpPayload($this->section, $this->picDept, [
            'what_text' => 'My own words',
            'generated_payload' => ['what_text' => 'Forged by the client'],
        ]))
        ->assertSessionHasNoErrors();

    $followUp = AlertFollowUp::query()->sole();

    expect($followUp->what_text)->toBe('My own words')
        ->and($followUp->generated_at)->not->toBeNull()
        ->and($followUp->generated_payload['what_text'])->toContain('100.0%')->toContain('10.0%')->toContain('2 consecutive days')
        ->and($followUp->generated_payload['when_date'])->toBe('2026-10-13')
        ->and($followUp->generated_payload['where_location'])->toBe($this->dept->name.' — '.$this->section->name)
        ->and($followUp->generated_payload['who_department_id'])->toBe($this->dept->id)
        ->and($followUp->generated_payload['why_text'])->toBe('');
});

test('sending queues an email to every active admin manager and team leader of the who department only', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create(['department_id' => $this->picDept->id]);
    $picManager = User::factory()->manager($this->picDept->id)->create();
    $picLeader = User::factory()->teamLeader(null, $this->picDept->id)->create();
    $inactive = User::factory()->manager($this->picDept->id)->inactive()->create();
    $operator = User::factory()->user()->create(['department_id' => $this->picDept->id]);
    $otherDeptManager = User::factory()->manager($this->dept->id)->create();

    $this->actingAs($this->manager)
        ->post(route('early-warning.follow-ups.store'), followUpPayload($this->section, $this->picDept) + ['send' => true])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Follow-up sent by email to the PIC department.');

    $followUp = AlertFollowUp::query()->sole();

    expect($followUp->status)->toBe(AlertFollowUpStatus::Sent)
        ->and($followUp->sent_at)->not->toBeNull()
        ->and($followUp->sent_by)->toBe($this->manager->id)
        ->and($followUp->streak_days)->toBe(2);

    foreach ([$admin, $picManager, $picLeader] as $recipient) {
        Mail::assertQueued(AlertFollowUpMail::class, fn (AlertFollowUpMail $mail) => $mail->hasTo($recipient->email));
    }

    foreach ([$inactive, $operator, $otherDeptManager] as $skipped) {
        Mail::assertNotQueued(AlertFollowUpMail::class, fn (AlertFollowUpMail $mail) => $mail->hasTo($skipped->email));
    }

    Mail::assertQueued(AlertFollowUpMail::class, 3);
});

test('sending requires the full 5W2H', function () {
    Mail::fake();
    User::factory()->manager($this->picDept->id)->create();

    $this->actingAs($this->manager)
        ->post(route('early-warning.follow-ups.store'), followUpPayload($this->section, $this->picDept, ['why_text' => '']) + ['send' => true])
        ->assertSessionHasErrors('why_text');

    expect(AlertFollowUp::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

test('sending fails without saving when the who department has no active recipients', function () {
    Mail::fake();

    $this->actingAs($this->manager)
        ->post(route('early-warning.follow-ups.store'), followUpPayload($this->section, $this->picDept) + ['send' => true])
        ->assertSessionHasErrors('who_department_id');

    expect(AlertFollowUp::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

test('a draft can be updated and then sent without changing its alert or its generated snapshot', function () {
    Mail::fake();
    User::factory()->manager($this->picDept->id)->create();

    $this->actingAs($this->manager)
        ->post(route('early-warning.follow-ups.store'), followUpPayload($this->section, $this->picDept, ['why_text' => null, 'how_text' => null]))
        ->assertSessionHasNoErrors();

    $draft = AlertFollowUp::query()->sole();
    $snapshot = $draft->generated_payload;

    $otherSection = Section::factory()->create(['department_id' => $this->dept->id]);

    $this->actingAs($this->manager)
        ->put(route('early-warning.follow-ups.update', $draft), followUpPayload($otherSection, $this->picDept, [
            'alert_date' => '2026-10-12',
            'what_text' => 'Masalah diperbarui',
        ]) + ['send' => true])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $draft->refresh();

    expect($draft->what_text)->toBe('Masalah diperbarui')
        ->and($draft->status)->toBe(AlertFollowUpStatus::Sent)
        ->and($draft->who_department_id)->toBe($this->picDept->id)
        ->and($draft->section_id)->toBe($this->section->id)
        ->and($draft->alert_date->toDateString())->toBe('2026-10-13')
        ->and($draft->generated_payload)->toBe($snapshot);

    Mail::assertQueued(AlertFollowUpMail::class, 1);
});

test('a sent follow-up is read-only', function () {
    Mail::fake();

    $sent = AlertFollowUp::factory()->forAlert($this->section, '2026-10-13')->sent()->create([
        'created_by' => $this->manager->id,
        'what_text' => 'Original',
    ]);

    $this->actingAs($this->manager)
        ->put(route('early-warning.follow-ups.update', $sent), followUpPayload($this->section, $this->picDept))
        ->assertForbidden();

    expect($sent->refresh()->what_text)->toBe('Original');
});

test('a date that is not an alert is rejected and nothing is saved', function () {
    Mail::fake();

    // Mon 12 Oct is Kuning (an alert); a day without approved hours is Hijau.
    $this->actingAs($this->manager)
        ->post(route('early-warning.follow-ups.store'), followUpPayload($this->section, $this->picDept, ['alert_date' => '2026-10-09']))
        ->assertSessionHasErrors(['alert_date' => 'A follow-up can only be saved for a date that is an alert (Yellow or Red).']);

    expect(AlertFollowUp::query()->count())->toBe(0);
});

test('an invalid alert date or unknown section fails validation', function () {
    $this->actingAs($this->manager)
        ->post(route('early-warning.follow-ups.store'), followUpPayload($this->section, $this->picDept, ['alert_date' => '13/10/2026']))
        ->assertSessionHasErrors('alert_date');

    $this->actingAs($this->manager)
        ->post(route('early-warning.follow-ups.store'), followUpPayload($this->section, $this->picDept, ['section_id' => 999999]))
        ->assertSessionHasErrors('section_id');

    expect(AlertFollowUp::query()->count())->toBe(0);
});

test('an alert holds one draft at a time while sent follow-ups pile up as history', function () {
    Mail::fake();
    User::factory()->manager($this->picDept->id)->create();

    $store = fn (array $extra = []) => $this->actingAs($this->manager)
        ->post(route('early-warning.follow-ups.store'), array_merge(followUpPayload($this->section, $this->picDept), $extra));

    $store()->assertSessionHasNoErrors();

    $store()->assertSessionHasErrors(['status' => 'This alert already has a draft. Edit the draft instead of creating a new one.']);
    expect(AlertFollowUp::query()->count())->toBe(1);

    AlertFollowUp::query()->sole()->update(['status' => AlertFollowUpStatus::Sent]);

    $store();
    $store(['alert_date' => '2026-10-12']);

    expect(AlertFollowUp::query()->count())->toBe(3)
        ->and(AlertFollowUp::query()->where('status', 'draft')->count())->toBe(2);
});

test('a manager cannot touch another department and operators are blocked', function () {
    $foreignSection = Section::factory()->create(['department_id' => $this->picDept->id]);
    $foreign = AlertFollowUp::factory()->forAlert($foreignSection, '2026-10-13')->create();

    $this->actingAs($this->manager)
        ->put(route('early-warning.follow-ups.update', $foreign), followUpPayload($foreignSection, $this->picDept))
        ->assertForbidden();

    $this->actingAs($this->manager)
        ->post(route('early-warning.follow-ups.store'), followUpPayload($foreignSection, $this->picDept))
        ->assertForbidden();

    $operator = User::factory()->user()->create(['department_id' => $this->dept->id]);

    $this->actingAs($operator)
        ->post(route('early-warning.follow-ups.store'), followUpPayload($this->section, $this->picDept))
        ->assertForbidden();

    expect(AlertFollowUp::query()->count())->toBe(1);
});

test('admin can follow up an alert of any department', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('early-warning.follow-ups.store'), followUpPayload($this->section, $this->picDept))
        ->assertSessionHasNoErrors();

    expect(AlertFollowUp::query()->sole()->department_id)->toBe($this->dept->id);
});

test('a legacy follow-up without an alert link cannot be edited', function () {
    $legacy = AlertFollowUp::factory()->create([
        'department_id' => $this->dept->id,
        'created_by' => $this->manager->id,
        'alert_date' => null,
    ]);

    $this->actingAs($this->manager)
        ->put(route('early-warning.follow-ups.update', $legacy), followUpPayload($this->section, $this->picDept))
        ->assertSessionHasErrors('alert_date');
});

test('the detail page opens the form with the system suggestion and the active who departments', function () {
    $inactive = Department::factory()->create(['is_active' => false]);

    $this->actingAs($this->manager)
        ->get(route('early-warning.show', [$this->section, '2026-10-13']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('early-warning/Show')
            ->where('suggestion.what_text', fn (string $what) => str_contains($what, '100.0%') && str_contains($what, '2 consecutive days'))
            ->where('suggestion.when_date', '2026-10-13')
            ->where('suggestion.who_department_id', $this->dept->id)
            ->where('suggestion.how_much_cost_idr', 0)
            ->where('departments', function ($departments) use ($inactive) {
                $ids = collect($departments)->pluck('id');

                return $ids->contains($this->picDept->id) && ! $ids->contains($inactive->id);
            }));
});

test('the detail page has no suggestion for a date that is not an alert', function () {
    $this->actingAs($this->manager)
        ->get(route('early-warning.show', [$this->section, '2026-10-09']))
        ->assertInertia(fn (Assert $page) => $page->where('alert', null)->where('suggestion', null));
});

test('the dashboard no longer exposes follow-ups', function () {
    AlertFollowUp::factory()->forAlert($this->section, '2026-10-13')->create();

    $this->actingAs($this->manager)
        ->get(route('dashboard', ['date' => '2026-10-14']))
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard')->missing('alertFollowUps'));
});

test('the follow-up email renders the 5W2H content, the streak and a link to the alert detail', function () {
    $followUp = AlertFollowUp::factory()->forAlert($this->section, '2026-10-13')->sent()->create([
        'who_department_id' => $this->picDept->id,
        'what_text' => 'Index harian melewati batas',
        'how_much_cost_idr' => 7500000,
        'streak_days' => 3,
        'index_pct' => 104.2,
    ]);

    $mail = new AlertFollowUpMail($followUp);

    $mail->assertSeeInHtml('Index harian melewati batas')
        ->assertSeeInHtml('Rp 7.500.000')
        ->assertSeeInHtml(e($this->picDept->name))
        ->assertSeeInHtml('13/10/2026')
        ->assertSeeInHtml('104,2%')
        ->assertSeeInHtml('3 consecutive days')
        ->assertSeeInHtml(route('early-warning.show', [$this->section, '2026-10-13']));
});

test('the email of a legacy follow-up links to the inbox instead of failing', function () {
    $followUp = AlertFollowUp::factory()->sent()->create(['alert_date' => null]);

    (new AlertFollowUpMail($followUp))->assertSeeInHtml(route('early-warning.index'));
});

test('the migration links legacy month-level follow-ups to the date they were created on', function () {
    $legacy = AlertFollowUp::factory()->create(['alert_date' => null, 'created_at' => '2026-09-30 23:30:00']);
    $linked = AlertFollowUp::factory()->forAlert($this->section, '2026-10-13')->create(['created_at' => '2026-10-14 08:00:00']);

    $migration = require database_path('migrations/2026_10_15_132911_add_generated_payload_to_alert_follow_ups_table.php');
    Closure::bind(fn () => $this->backfillLegacyAlertDates(), $migration, $migration::class)();

    expect($legacy->refresh()->alert_date->toDateString())->toBe('2026-09-30')
        ->and($linked->refresh()->alert_date->toDateString())->toBe('2026-10-13');
});
