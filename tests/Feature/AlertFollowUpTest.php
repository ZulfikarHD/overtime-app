<?php

use App\Enums\AlertFollowUpStatus;
use App\Mail\AlertFollowUpMail;
use App\Models\AlertFollowUp;
use App\Models\Department;
use App\Models\PolicyThreshold;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    PolicyThreshold::factory()->plantDefault()->create();

    $this->dept = Department::factory()->create(['is_active' => true]);
    $this->picDept = Department::factory()->create(['is_active' => true]);
    $this->manager = User::factory()->manager($this->dept->id)->create();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function followUpPayload(Department $picDept, array $overrides = []): array
{
    return array_merge([
        'date' => '2026-10-14',
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

test('manager saves a draft with server-computed zone and no email', function () {
    Mail::fake();

    $this->actingAs($this->manager)
        ->post(route('dashboard.alert-follow-ups.store'), followUpPayload($this->picDept, [
            'why_text' => null,
            'how_text' => null,
        ]))
        ->assertRedirect();

    $followUp = AlertFollowUp::query()->sole();

    expect($followUp->status)->toBe(AlertFollowUpStatus::Draft)
        ->and($followUp->department_id)->toBe($this->dept->id)
        ->and($followUp->created_by)->toBe($this->manager->id)
        ->and($followUp->zone->value)->toBeIn(['green', 'yellow', 'red'])
        ->and($followUp->how_much_cost_idr)->toBe(7500000)
        ->and($followUp->sent_at)->toBeNull();

    Mail::assertNothingQueued();
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
        ->post(route('dashboard.alert-follow-ups.store'), followUpPayload($this->picDept) + ['send' => true])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $followUp = AlertFollowUp::query()->sole();

    expect($followUp->status)->toBe(AlertFollowUpStatus::Sent)
        ->and($followUp->sent_at)->not->toBeNull()
        ->and($followUp->sent_by)->toBe($this->manager->id);

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
        ->post(route('dashboard.alert-follow-ups.store'), followUpPayload($this->picDept, ['why_text' => '']) + ['send' => true])
        ->assertSessionHasErrors('why_text');

    expect(AlertFollowUp::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

test('sending fails without saving when the who department has no active recipients', function () {
    Mail::fake();

    $this->actingAs($this->manager)
        ->post(route('dashboard.alert-follow-ups.store'), followUpPayload($this->picDept) + ['send' => true])
        ->assertSessionHasErrors('who_department_id');

    expect(AlertFollowUp::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

test('a draft can be updated and then sent', function () {
    Mail::fake();
    User::factory()->manager($this->picDept->id)->create();

    $draft = AlertFollowUp::factory()->create([
        'department_id' => $this->dept->id,
        'created_by' => $this->manager->id,
    ]);

    $this->actingAs($this->manager)
        ->put(route('dashboard.alert-follow-ups.update', $draft), followUpPayload($this->picDept, [
            'what_text' => 'Masalah diperbarui',
        ]) + ['send' => true])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $draft->refresh();

    expect($draft->what_text)->toBe('Masalah diperbarui')
        ->and($draft->status)->toBe(AlertFollowUpStatus::Sent)
        ->and($draft->who_department_id)->toBe($this->picDept->id);

    Mail::assertQueued(AlertFollowUpMail::class, 1);
});

test('a sent follow-up is read-only', function () {
    Mail::fake();

    $sent = AlertFollowUp::factory()->sent()->create([
        'department_id' => $this->dept->id,
        'created_by' => $this->manager->id,
        'what_text' => 'Original',
    ]);

    $this->actingAs($this->manager)
        ->put(route('dashboard.alert-follow-ups.update', $sent), followUpPayload($this->picDept))
        ->assertForbidden();

    expect($sent->refresh()->what_text)->toBe('Original');
});

test('a manager cannot edit another department follow-up and operators are blocked', function () {
    $foreign = AlertFollowUp::factory()->create(['department_id' => $this->picDept->id]);

    $this->actingAs($this->manager)
        ->put(route('dashboard.alert-follow-ups.update', $foreign), followUpPayload($this->picDept))
        ->assertForbidden();

    $operator = User::factory()->user()->create(['department_id' => $this->dept->id]);

    $this->actingAs($operator)
        ->post(route('dashboard.alert-follow-ups.store'), followUpPayload($this->picDept))
        ->assertForbidden();
});

test('dashboard exposes follow-ups of the selected scope and period only', function () {
    AlertFollowUp::factory()->create([
        'department_id' => $this->dept->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 10,
        'what_text' => 'Visible follow-up',
    ]);
    AlertFollowUp::factory()->create([
        'department_id' => $this->dept->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
    ]);
    AlertFollowUp::factory()->create([
        'department_id' => $this->picDept->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 10,
    ]);

    $this->actingAs($this->manager)
        ->get(route('dashboard', ['date' => '2026-10-14']))
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('alertFollowUps', 1)
            ->where('alertFollowUps.0.what_text', 'Visible follow-up')
            ->where('alertFollowUps.0.is_editable', true)
        );
});

test('the follow-up email renders the 5W2H content', function () {
    $followUp = AlertFollowUp::factory()->sent()->create([
        'department_id' => $this->dept->id,
        'who_department_id' => $this->picDept->id,
        'what_text' => 'Index harian melewati batas',
        'how_much_cost_idr' => 7500000,
    ]);

    $mail = new AlertFollowUpMail($followUp);

    $mail->assertSeeInHtml('Index harian melewati batas')
        ->assertSeeInHtml('Rp 7.500.000')
        ->assertSeeInHtml($this->picDept->name);
});
