<?php

use App\Models\Department;
use App\Models\PolicyThreshold;
use App\Models\User;
use App\Services\PolicyThresholdService;
use Inertia\Testing\AssertableInertia as Assert;

test('guest is redirected to login when accessing administration hub', function () {
    $this->get(route('admin.administration'))->assertRedirect(route('login'));
});

test('non-admin users are denied access to administration hub and policy threshold routes', function () {
    $manager = User::factory()->manager()->create();
    $teamLeader = User::factory()->teamLeader()->create();
    $operator = User::factory()->user()->create();

    $this->actingAs($manager)->get(route('admin.administration'))->assertForbidden();
    $this->actingAs($teamLeader)->get(route('admin.administration'))->assertForbidden();
    $this->actingAs($operator)->get(route('admin.administration'))->assertForbidden();

    $this->actingAs($manager)->post(route('admin.policy-thresholds.store'), [
        'weekly_soft_limit_hours' => 20,
        'consecutive_weeks_alert' => 3,
        'spkl_grace_period_days' => 2,
        'burn_warning_pct' => 100,
        'burn_danger_pct' => 115,
    ])->assertForbidden();

    $plantDefault = PolicyThreshold::factory()->plantDefault()->create();

    $this->actingAs($teamLeader)->put(route('admin.policy-thresholds.update', $plantDefault), [
        'weekly_soft_limit_hours' => 25,
        'consecutive_weeks_alert' => 4,
        'spkl_grace_period_days' => 3,
        'burn_warning_pct' => 100,
        'burn_danger_pct' => 120,
    ])->assertForbidden();

    $this->actingAs($operator)->delete(route('admin.policy-thresholds.destroy', $plantDefault))->assertForbidden();
});

test('admin can access administration hub with plant default and department status props', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create([
        'code' => 'DEPT_STP',
        'name' => 'Stamping Department',
    ]);

    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
        'spkl_grace_period_days' => 2,
        'burn_warning_pct' => 100.00,
        'burn_danger_pct' => 115.00,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.administration'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/Administration')
        ->where('activeTab', 'policies')
        ->has('plantDefault', fn (Assert $plantDefault) => $plantDefault
            ->where('weekly_soft_limit_hours', fn ($val) => (float) $val == 20.0)
            ->where('consecutive_weeks_alert', 3)
            ->where('spkl_grace_period_days', 2)
            ->where('burn_warning_pct', fn ($val) => (float) $val == 100.0)
            ->where('burn_danger_pct', fn ($val) => (float) $val == 115.0)
            ->etc()
        )
        ->has('departments', 1, fn (Assert $item) => $item
            ->where('department_code', 'DEPT_STP')
            ->where('has_override', false)
            ->where('weekly_soft_limit_hours', fn ($val) => (float) $val == 20.0)
            ->etc()
        )
        ->has('policyStats')
        ->where('policyStats.total_departments', 1)
        ->where('policyStats.overrides_count', 0)
        ->where('policyStats.inherited_count', 1)
    );
});

test('admin can update plant-wide default policy threshold', function () {
    $admin = User::factory()->admin()->create();

    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
        'spkl_grace_period_days' => 2,
        'burn_warning_pct' => 100.00,
        'burn_danger_pct' => 115.00,
    ]);

    $payload = [
        'department_id' => null,
        'weekly_soft_limit_hours' => 25.5,
        'consecutive_weeks_alert' => 4,
        'spkl_grace_period_days' => 3,
        'burn_warning_pct' => 105.00,
        'burn_danger_pct' => 120.00,
    ];

    $response = $this->actingAs($admin)->post(route('admin.policy-thresholds.store'), $payload);

    $response->assertRedirect(route('admin.administration', ['tab' => 'policies']));
    $response->assertSessionHas('success', __('Plant-wide default policy threshold updated successfully.'));

    $this->assertDatabaseHas('policy_thresholds', [
        'department_id' => null,
        'weekly_soft_limit_hours' => 25.5,
        'consecutive_weeks_alert' => 4,
        'spkl_grace_period_days' => 3,
        'burn_warning_pct' => 105.00,
        'burn_danger_pct' => 120.00,
    ]);
});

test('admin can update plant default via put route', function () {
    $admin = User::factory()->admin()->create();

    $plantDefault = PolicyThreshold::factory()->plantDefault()->create();

    $response = $this->actingAs($admin)->put(route('admin.policy-thresholds.update', $plantDefault), [
        'department_id' => null,
        'weekly_soft_limit_hours' => 22.0,
        'consecutive_weeks_alert' => 2,
        'spkl_grace_period_days' => 4,
        'burn_warning_pct' => 95.00,
        'burn_danger_pct' => 110.00,
    ]);

    $response->assertRedirect(route('admin.administration', ['tab' => 'policies']));
    $response->assertSessionHas('success', __('Plant-wide default policy threshold updated successfully.'));

    $this->assertDatabaseHas('policy_thresholds', [
        'id' => $plantDefault->id,
        'department_id' => null,
        'weekly_soft_limit_hours' => 22.0,
        'consecutive_weeks_alert' => 2,
        'spkl_grace_period_days' => 4,
        'burn_warning_pct' => 95.00,
        'burn_danger_pct' => 110.00,
    ]);
});

test('admin can create department policy override', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create(['code' => 'DEPT_ASY']);

    PolicyThreshold::factory()->plantDefault()->create();

    $payload = [
        'department_id' => $dept->id,
        'weekly_soft_limit_hours' => 30.0,
        'consecutive_weeks_alert' => 5,
        'spkl_grace_period_days' => 5,
        'burn_warning_pct' => 110.00,
        'burn_danger_pct' => 125.00,
    ];

    $response = $this->actingAs($admin)->post(route('admin.policy-thresholds.store'), $payload);

    $response->assertRedirect(route('admin.administration', ['tab' => 'policies']));
    $response->assertSessionHas('success', __('Department policy override saved successfully.'));

    $this->assertDatabaseHas('policy_thresholds', [
        'department_id' => $dept->id,
        'weekly_soft_limit_hours' => 30.0,
        'consecutive_weeks_alert' => 5,
        'spkl_grace_period_days' => 5,
        'burn_warning_pct' => 110.00,
        'burn_danger_pct' => 125.00,
    ]);
});

test('admin can update an existing department policy override', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create();

    $override = PolicyThreshold::factory()->forDepartment($dept)->create([
        'weekly_soft_limit_hours' => 24.0,
        'consecutive_weeks_alert' => 3,
        'spkl_grace_period_days' => 2,
        'burn_warning_pct' => 100.00,
        'burn_danger_pct' => 115.00,
    ]);

    $response = $this->actingAs($admin)->put(route('admin.policy-thresholds.update', $override), [
        'department_id' => $dept->id,
        'weekly_soft_limit_hours' => 28.0,
        'consecutive_weeks_alert' => 4,
        'spkl_grace_period_days' => 3,
        'burn_warning_pct' => 105.00,
        'burn_danger_pct' => 118.00,
    ]);

    $response->assertRedirect(route('admin.administration', ['tab' => 'policies']));
    $response->assertSessionHas('success', __('Department policy override updated successfully.'));

    $this->assertDatabaseHas('policy_thresholds', [
        'id' => $override->id,
        'department_id' => $dept->id,
        'weekly_soft_limit_hours' => 28.0,
        'burn_danger_pct' => 118.00,
    ]);
});

test('admin can delete department override reverting it to plant default', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create();

    $override = PolicyThreshold::factory()->forDepartment($dept)->create();

    $response = $this->actingAs($admin)->delete(route('admin.policy-thresholds.destroy', $override));

    $response->assertRedirect(route('admin.administration', ['tab' => 'policies']));
    $response->assertSessionHas('success', __('Department policy override deleted successfully. Department will inherit plant default.'));

    $this->assertDatabaseMissing('policy_thresholds', [
        'id' => $override->id,
    ]);
});

test('plant default threshold cannot be deleted', function () {
    $admin = User::factory()->admin()->create();
    $plantDefault = PolicyThreshold::factory()->plantDefault()->create();

    $response = $this->actingAs($admin)->delete(route('admin.policy-thresholds.destroy', $plantDefault));

    $response->assertSessionHasErrors(['policy_threshold']);

    $this->assertDatabaseHas('policy_thresholds', [
        'id' => $plantDefault->id,
        'department_id' => null,
    ]);
});

test('policy threshold service correctly resolves hierarchical inheritance', function () {
    $service = app(PolicyThresholdService::class);

    $plantDefault = PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'spkl_grace_period_days' => 2,
    ]);

    $deptA = Department::factory()->create(['code' => 'DEPT_INHERIT']);
    $deptB = Department::factory()->create(['code' => 'DEPT_CUSTOM']);

    PolicyThreshold::factory()->forDepartment($deptB)->create([
        'weekly_soft_limit_hours' => 26.0,
        'spkl_grace_period_days' => 4,
    ]);

    // Department without override inherits plant default
    $resolvedA = $service->getForDepartment($deptA->id);
    expect($resolvedA->id)->toBe($plantDefault->id)
        ->and((float) $resolvedA->weekly_soft_limit_hours)->toBe(20.0)
        ->and((int) $resolvedA->spkl_grace_period_days)->toBe(2);

    // Department with override gets its custom values
    $resolvedB = $service->getForDepartment($deptB->id);
    expect($resolvedB->department_id)->toBe($deptB->id)
        ->and((float) $resolvedB->weekly_soft_limit_hours)->toBe(26.0)
        ->and((int) $resolvedB->spkl_grace_period_days)->toBe(4);

    // Passing null resolves plant default
    $resolvedNull = $service->getForDepartment(null);
    expect($resolvedNull->id)->toBe($plantDefault->id);
});

test('policy threshold service auto-creates plant default if database is unseeded', function () {
    PolicyThreshold::query()->delete();

    $service = app(PolicyThresholdService::class);
    $default = $service->getPlantDefault();

    expect($default)->not->toBeNull()
        ->and($default->department_id)->toBeNull()
        ->and((float) $default->weekly_soft_limit_hours)->toBe(20.0)
        ->and((int) $default->consecutive_weeks_alert)->toBe(3)
        ->and((int) $default->spkl_grace_period_days)->toBe(2)
        ->and((float) $default->burn_warning_pct)->toBe(100.00)
        ->and((float) $default->burn_danger_pct)->toBe(115.00);

    $this->assertDatabaseHas('policy_thresholds', [
        'department_id' => null,
        'weekly_soft_limit_hours' => 20.0,
    ]);
});

test('validation rejects invalid threshold inputs', function () {
    $admin = User::factory()->admin()->create();

    // 1. Negative weekly soft limit
    $this->actingAs($admin)->post(route('admin.policy-thresholds.store'), [
        'weekly_soft_limit_hours' => -5,
        'consecutive_weeks_alert' => 3,
        'spkl_grace_period_days' => 2,
        'burn_warning_pct' => 100,
        'burn_danger_pct' => 115,
    ])->assertSessionHasErrors(['weekly_soft_limit_hours']);

    // 2. Weekly soft limit > 168 hours
    $this->actingAs($admin)->post(route('admin.policy-thresholds.store'), [
        'weekly_soft_limit_hours' => 200,
        'consecutive_weeks_alert' => 3,
        'spkl_grace_period_days' => 2,
        'burn_warning_pct' => 100,
        'burn_danger_pct' => 115,
    ])->assertSessionHasErrors(['weekly_soft_limit_hours']);

    // 3. Consecutive weeks < 1 or > 52
    $this->actingAs($admin)->post(route('admin.policy-thresholds.store'), [
        'weekly_soft_limit_hours' => 20,
        'consecutive_weeks_alert' => 0,
        'spkl_grace_period_days' => 2,
        'burn_warning_pct' => 100,
        'burn_danger_pct' => 115,
    ])->assertSessionHasErrors(['consecutive_weeks_alert']);

    // 4. Negative SPKL grace period or > 30 days
    $this->actingAs($admin)->post(route('admin.policy-thresholds.store'), [
        'weekly_soft_limit_hours' => 20,
        'consecutive_weeks_alert' => 3,
        'spkl_grace_period_days' => 45,
        'burn_warning_pct' => 100,
        'burn_danger_pct' => 115,
    ])->assertSessionHasErrors(['spkl_grace_period_days']);

    // 5. Illogical burn percentages: danger < warning
    $this->actingAs($admin)->post(route('admin.policy-thresholds.store'), [
        'weekly_soft_limit_hours' => 20,
        'consecutive_weeks_alert' => 3,
        'spkl_grace_period_days' => 2,
        'burn_warning_pct' => 115,
        'burn_danger_pct' => 100, // danger is less than warning!
    ])->assertSessionHasErrors(['burn_danger_pct']);

    // 6. Non-existent department_id
    $this->actingAs($admin)->post(route('admin.policy-thresholds.store'), [
        'department_id' => 999999,
        'weekly_soft_limit_hours' => 20,
        'consecutive_weeks_alert' => 3,
        'spkl_grace_period_days' => 2,
        'burn_warning_pct' => 100,
        'burn_danger_pct' => 115,
    ])->assertSessionHasErrors(['department_id']);
});
