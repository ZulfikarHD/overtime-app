# Feature Flags — Financial Hub & Approvals Soft-Disable

## Status (current stage)

| Flag                      | Config key                              | Default | Effect when `false`                                                                           |
| ------------------------- | --------------------------------------- | ------- | --------------------------------------------------------------------------------------------- |
| Financial & Governance    | `features.financial_governance_enabled` | `false` | CapEx hub, Burn Index, Analytics, Budget Planning routes return **403**; sidebar group hidden |
| Overtime Approvals        | `features.overtime_approvals_enabled`   | `false` | Approvals queue routes return **403**; SPL import and daily submissions **auto-approve**      |
| CapEx attribution (BR-08) | `features.capex_attribution_required`   | `false` | `hours_project` may be set without `capex_project_id`                                         |

Env overrides (optional): `FEATURE_FINANCIAL_GOVERNANCE`, `FEATURE_OVERTIME_APPROVALS`, `FEATURE_CAPEX_ATTRIBUTION_REQUIRED`.

## What stays visible

- Operasional & Lembur: Dashboard, Planning OT, Input Lembur (SPL), Employee Reports
- Sistem & Konfigurasi (admin)
- Overtime **Project** hour category (`hours_project`) — not CapEx master data
- Section hour plafon indicator (`OvertimeBudget.planned_hours`) on submission flows — no Budget Planning page, no Rupiah UI on OT surfaces
- Branding: **ISUZU Overtime** (no CapEx wording)

## Re-enable

Set the env vars to `true` (or flip `config/features.php` defaults). Sidebar/nav still needs the Financial & Approvals items restored in `AppSidebar.vue` if they were removed rather than gated by shared `features` props.

## Tests

`tests/TestCase.php` enables all three flags so legacy CapEx/approval suites keep working. `FeatureGateTest` and `FeatureGateNavBrowserTest` explicitly turn them off.
