<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Existing CapEx / Burn / Analytics / Budget / Approval suites expect features on.
        // Production defaults remain false via config/features.php.
        config([
            'features.financial_governance_enabled' => true,
            'features.overtime_approvals_enabled' => true,
            'features.capex_attribution_required' => true,
        ]);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
