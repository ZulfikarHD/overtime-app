<?php

use App\Models\User;
use App\Support\Features;

test('financial governance routes return 403 when feature is disabled', function () {
    config(['features.financial_governance_enabled' => false]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.capex-projects.index'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('dashboard.burn-index'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('analytics.index'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('budgets.planning'))
        ->assertForbidden();
});

test('overtime approval routes return 403 when feature is disabled', function () {
    config(['features.overtime_approvals_enabled' => false]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('overtime.approvals'))
        ->assertForbidden();
});

test('financial governance routes are reachable when feature is enabled', function () {
    config(['features.financial_governance_enabled' => true]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('budgets.planning'))
        ->assertOk();
});

test('features helper reads config flags', function () {
    config([
        'features.financial_governance_enabled' => false,
        'features.overtime_approvals_enabled' => true,
        'features.capex_attribution_required' => false,
    ]);

    expect(Features::financialGovernanceEnabled())->toBeFalse()
        ->and(Features::overtimeApprovalsEnabled())->toBeTrue()
        ->and(Features::capexAttributionRequired())->toBeFalse();
});
