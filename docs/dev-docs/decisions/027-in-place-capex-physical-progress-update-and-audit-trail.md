# ADR-027: In-Place CapEx Physical Progress Update & Audit Trail

**Date:** 2026-09-09
**Status:** accepted

## Context

Tracking capitalized labor costs against capital projects requires comparing labor burn against physical shopfloor completion. In Story E07-05 (Epic-07), Project Managers and Administrators need to update project physical completion percentages (`physical_progress_pct`, 0.0% to 100.0%) to accurately calculate the Milestone Burn Ratio:

$$\text{Milestone Burn Ratio} = \frac{\text{CapEx Burn Index}}{\text{Physical Progress \%}}$$

Prior to this implementation:

1. Updating physical completion required navigating away from the cockpit or full-page form reloads, causing UI flicker, scroll disruption, and loss of context.
2. Changes to physical completion lacked an explicit, traceable audit log linking the author, previous progress, new progress, and timestamp.
3. Reaching 100% completion had no automated trigger prompting managers to close or complete the project lifecycle.
4. Out-of-bounds or non-numeric inputs could cause division artifacts or invalid data states.

## Decision

We designed and implemented the in-place physical progress update mechanism, headless audit trail, and milestone completion prompts with the following architectural principles:

1. **In-Place Reactive Editing Without Full-Page Reloads**:
   Built `<InlineProgressEditor>` in `resources/js/components/capex/InlineProgressEditor.vue`. It provides dual input controls: an interactive slider (`min="0"`, `max="100"`, `step="0.5"`) and a manual numeric input field with automatic clamping on blur and save (`0.0` to `100.0`).
   Updates are dispatched via `router.patch` using Wayfinder typed route `capexProjectsRoute.progress.update.url({ capex_project: projectId })` with `preserveScroll: true`. The backend redirects back, allowing Inertia to update page props without full-page reloads.

2. **Headless Audit Logging in `overtime_item_audits`**:
   Every update to `physical_progress_pct` writes an immutable record to `overtime_item_audits` with `action = 'PROGRESS_UPDATE'`, `actor_user_id = $user->id`, and states containing `capex_project_id`, `previous_pct`, `new_pct`, and descriptive notes. The `OvertimeItemAudit` model's immutability observer prevents subsequent mutation or deletion.

3. **Author Attribution in Cockpit Metrics**:
   `CapexProjectService@getProjectDetail` queries the latest `PROGRESS_UPDATE` audit record for the project and attaches `last_progress_update` (`actor_name`, `actor_npk`, `updated_at`, `updated_at_diff`, `previous_pct`, `new_pct`) to the cockpit metrics payload. The cockpit displays this attribution notice (`Diperbarui oleh :name, :time`) in display mode.

4. **100% Completion Milestone Prompt**:
   When physical progress reaches `100.0%`, the cockpit dynamically renders an in-page celebratory banner (`[data-test="completion-milestone-banner"]`). The banner allows the manager to either immediately open `ProjectStatusTransitionModal` with `COMPLETED` pre-selected or dismiss the prompt (`Nanti Saja`) while retaining 100% progress.

5. **Immediate Milestone Burn Ratio Re-evaluation & Alerting**:
   Immediately upon saving new physical progress, `CapexAccountingService@evaluateAndNotifyBurnAlert` evaluates whether the project crosses warning thresholds, and the Milestone Burn Ratio is recalculated reactively in the frontend cockpit.

## Consequences

### Positive

- **Ergonomics & Zero Flicker**: PMs and factory supervisors can update shopfloor completion in seconds directly from the cockpit without losing their position on burndown charts.
- **Audit Compliance**: Complete auditability of every progress modification with historical values, actor NPK/name, IP address, and timestamp.
- **Workflow Continuity**: The 100% milestone prompt bridges the gap between physical shopfloor execution and administrative project lifecycle closure.
- **Robust Guardrails**: Automatic value clamping and backend form request validation ensure percentage values strictly conform to [0.0, 100.0].

### Negative

- **Audit Table Overloading**: Storing project progress updates in `overtime_item_audits` with `overtime_item_id = null` requires polymorphic JSON querying (`new_state->capex_project_id`). This was mitigated with nullable foreign keys and JSON path extraction.

### Neutral

- **Translation Keys**: Added multilingual keys to `lang/id.json` and `lang/en.json` for update attribution and status prompt notices.
