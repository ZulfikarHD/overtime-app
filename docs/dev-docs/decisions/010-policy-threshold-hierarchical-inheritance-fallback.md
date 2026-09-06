# ADR-010: Policy Threshold Hierarchical Inheritance Fallback

**Date:** 2026-09-07  
**Status:** accepted

## Context

In an industrial manufacturing plant, different production departments operate under varying operational intensities (e.g. Stamping and Assembly lines may require distinct overtime soft limits and budget burn thresholds compared to Logistics or Quality Assurance). However, maintaining independent policy records for dozens of departments from scratch is tedious, error-prone, and creates administrative overhead during company-wide policy adjustments.

Furthermore, timesheet validation (`SubmitOvertimeAction`), worker fatigue alerts, and budget burn analytics (`BurnIndexCalculator`) require a reliable, unified method to retrieve threshold values without redundant branching logic across multiple services.

## Decision

We implemented a centralized **Hierarchical Inheritance Engine** encapsulated within `PolicyThresholdService`:

1. **Unified Table Representation**: All thresholds are stored in `policy_thresholds`. Records with `department_id = null` represent the plant-wide default; records with a non-null `department_id` represent departmental overrides.
2. **Transparent Fallback Method**: `PolicyThresholdService::getForDepartment(?int $departmentId): PolicyThreshold` automatically checks for a departmental override. If absent (or if `$departmentId` is null), it transparently falls back to `getPlantDefault()`.
3. **Resilient Auto-initialization**: If the database lacks a plant-wide default (e.g. before initial database seeding), `getPlantDefault()` automatically seeds baseline defaults (20.0 hrs/wk, 3 weeks alert, 2 days grace, 100% warning, 115% danger) so consumers never encounter null pointer exceptions.
4. **Reversible Override Deletion**: Deleting an override record safely reverts the department to inheriting the plant-wide default without destroying historical overtime records or timesheet audit trails.
5. **Plant Default Deletion Guard**: Attempts to delete the plant-wide default threshold are intercepted and blocked at the service and request layer.

## Consequences

### Positive

- **Zero Consumer Boilerplate**: Downstream consumers (timesheets, SPKL validation, burn index calculation) make a single call to `PolicyThresholdService::getForDepartment($deptId)` without writing custom fallback logic.
- **Instant Plant-Wide Propagation**: Updating the plant-wide baseline automatically updates all inheriting departments on their next page load without requiring bulk updates.
- **Granular Operational Flexibility**: High-demand production lines can be granted custom limits without altering factory-wide baselines.
- **Administrative Safety**: Plant baseline cannot be accidentally dropped, and removing an override is an instant, non-destructive fallback to the plant default.

### Negative

- **Slight Lookup Overhead**: Each departmental query involves an override check before resolving the default. However, this is negligible given indexed lookups and typical production department counts (\(\le 50\)).

### Neutral

- **Non-Retroactive Application**: Updates to SPKL grace periods and soft limits apply strictly to new submissions; historical overtime submissions retain their stamped audit states.
