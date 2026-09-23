<?php

namespace App\Support;

class Features
{
    public static function enabled(string $feature): bool
    {
        return (bool) config("features.{$feature}", false);
    }

    public static function financialGovernanceEnabled(): bool
    {
        return self::enabled('financial_governance_enabled');
    }

    public static function overtimeApprovalsEnabled(): bool
    {
        return self::enabled('overtime_approvals_enabled');
    }

    public static function capexAttributionRequired(): bool
    {
        return self::enabled('capex_attribution_required');
    }
}
