<?php

use App\Models\PolicyThreshold;
use App\Models\ShiftSchedule;
use App\Models\User;
use App\Services\ShiftScheduleService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    PolicyThreshold::factory()->plantDefault()->create();
});

test('guest is redirected to login when storing shift schedules', function () {
    $this->post(route('admin.shift-schedules.store'), [])
        ->assertRedirect(route('login'));
});

test('non-admin users are forbidden from managing shift schedules', function () {
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager)
        ->post(route('admin.shift-schedules.store'), [
            'name' => 'Unauthorized',
            'effective_from' => '2026-01-01',
            'shift_1_start' => '07:00',
            'shift_1_end' => '15:00',
            'shift_2_start' => '15:00',
            'shift_2_end' => '23:00',
            'shift_3_start' => '23:00',
            'shift_3_end' => '07:00',
        ])
        ->assertForbidden();
});

test('admin can access administration hub shifts tab with schedule props', function () {
    $admin = User::factory()->admin()->create();
    ShiftSchedule::factory()->create([
        'name' => 'Standard Plant Shift',
        'effective_from' => '2026-01-01',
        'effective_until' => null,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.administration', ['tab' => 'shifts']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/Administration')
        ->where('activeTab', 'shifts')
        ->has('shiftSchedules', 1)
        ->where('shiftSchedules.0.name', 'Standard Plant Shift')
        ->has('activeShiftSchedule.shifts', 3)
    );
});

test('admin can create a new shift schedule period', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.shift-schedules.store'), [
        'name' => 'Ramadan 2026',
        'effective_from' => '2026-03-01',
        'effective_until' => '2026-03-31',
        'shift_1_start' => '06:00',
        'shift_1_end' => '14:00',
        'shift_2_start' => '14:00',
        'shift_2_end' => '22:00',
        'shift_3_start' => '22:00',
        'shift_3_end' => '06:00',
    ]);

    $response->assertRedirect(route('admin.administration', ['tab' => 'shifts']));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('shift_schedules', [
        'name' => 'Ramadan 2026',
        'shift_1_start' => '06:00',
        'shift_2_start' => '14:00',
        'shift_3_start' => '22:00',
    ]);
});

test('creating a new open period auto-closes the previous open-ended schedule', function () {
    $admin = User::factory()->admin()->create();

    $previous = ShiftSchedule::factory()->create([
        'name' => 'Standard',
        'effective_from' => '2026-01-01',
        'effective_until' => null,
    ]);

    $this->actingAs($admin)->post(route('admin.shift-schedules.store'), [
        'name' => 'Q2 Adjustment',
        'effective_from' => '2026-04-01',
        'effective_until' => null,
        'shift_1_start' => '07:30',
        'shift_1_end' => '15:30',
        'shift_2_start' => '15:30',
        'shift_2_end' => '23:30',
        'shift_3_start' => '23:30',
        'shift_3_end' => '07:30',
    ])->assertRedirect();

    expect($previous->fresh()->effective_until?->toDateString())->toBe('2026-03-31');
});

test('admin can update and delete a shift schedule', function () {
    $admin = User::factory()->admin()->create();
    $schedule = ShiftSchedule::factory()->create([
        'name' => 'Editable',
        'effective_from' => '2026-01-01',
        'effective_until' => '2026-06-30',
    ]);

    $this->actingAs($admin)->put(route('admin.shift-schedules.update', $schedule), [
        'name' => 'Edited Period',
        'effective_from' => '2026-01-01',
        'effective_until' => '2026-06-30',
        'shift_1_start' => '08:00',
        'shift_1_end' => '16:00',
        'shift_2_start' => '16:00',
        'shift_2_end' => '00:00',
        'shift_3_start' => '00:00',
        'shift_3_end' => '08:00',
    ])->assertRedirect(route('admin.administration', ['tab' => 'shifts']));

    expect($schedule->fresh()->name)->toBe('Edited Period')
        ->and($schedule->fresh()->formatTime('shift_1_start'))->toBe('08:00');

    $this->actingAs($admin)
        ->delete(route('admin.shift-schedules.destroy', $schedule))
        ->assertRedirect(route('admin.administration', ['tab' => 'shifts']));

    $this->assertDatabaseMissing('shift_schedules', ['id' => $schedule->id]);
});

test('validation rejects non-contiguous shift windows', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.shift-schedules.store'), [
        'name' => 'Broken',
        'effective_from' => '2026-01-01',
        'shift_1_start' => '07:00',
        'shift_1_end' => '15:00',
        'shift_2_start' => '16:00',
        'shift_2_end' => '23:00',
        'shift_3_start' => '23:00',
        'shift_3_end' => '07:00',
    ])->assertSessionHasErrors('shift_2_start');
});

test('service enforces maximum three periods intersecting one calendar year', function () {
    ShiftSchedule::factory()->create([
        'name' => 'P1',
        'effective_from' => '2026-01-01',
        'effective_until' => '2026-03-31',
    ]);
    ShiftSchedule::factory()->create([
        'name' => 'P2',
        'effective_from' => '2026-04-01',
        'effective_until' => '2026-06-30',
    ]);
    ShiftSchedule::factory()->create([
        'name' => 'P3',
        'effective_from' => '2026-07-01',
        'effective_until' => '2026-12-31',
    ]);

    $service = app(ShiftScheduleService::class);

    expect(fn () => $service->create([
        'name' => 'P4',
        'effective_from' => '2026-10-01',
        'effective_until' => '2026-10-15',
        'shift_1_start' => '07:00',
        'shift_1_end' => '15:00',
        'shift_2_start' => '15:00',
        'shift_2_end' => '23:00',
        'shift_3_start' => '23:00',
        'shift_3_end' => '07:00',
    ]))->toThrow(ValidationException::class);
});

test('active schedule payload falls back to defaults when empty', function () {
    $payload = app(ShiftScheduleService::class)->getActivePayload(
        Carbon::parse('2026-05-01', 'Asia/Jakarta'),
    );

    expect($payload['id'])->toBeNull()
        ->and($payload['shifts'])->toHaveCount(3)
        ->and($payload['shifts'][0]['start'])->toBe('07:00');
});

test('shared inertia props include active shift schedule', function () {
    ShiftSchedule::factory()->create([
        'name' => 'Shared',
        'effective_from' => '2026-01-01',
        'shift_1_start' => '06:30',
        'shift_1_end' => '14:30',
        'shift_2_start' => '14:30',
        'shift_2_end' => '22:30',
        'shift_3_start' => '22:30',
        'shift_3_end' => '06:30',
    ]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('shiftSchedule')
            ->where('shiftSchedule.shifts.0.start', '06:30')
        );
});
