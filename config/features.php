<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Financial & Governance Hub
    |--------------------------------------------------------------------------
    |
    | When false, CapEx projects, Burn Index, Analytics, and Budget Planning
    | routes return 403 and are hidden from navigation.
    |
    */
    'financial_governance_enabled' => (bool) env('FEATURE_FINANCIAL_GOVERNANCE', false),

    /*
    |--------------------------------------------------------------------------
    | Overtime Approvals Queue
    |--------------------------------------------------------------------------
    |
    | When false, approval routes return 403. SPL imports and submissions
    | auto-approve so hours still feed reports and burn indicators.
    |
    */
    'overtime_approvals_enabled' => (bool) env('FEATURE_OVERTIME_APPROVALS', false),

    /*
    |--------------------------------------------------------------------------
    | CapEx Attribution Requirement (BR-08)
    |--------------------------------------------------------------------------
    |
    | When false, hours_project may be > 0 without a capex_project_id.
    |
    */
    'capex_attribution_required' => (bool) env('FEATURE_CAPEX_ATTRIBUTION_REQUIRED', false),

];
