# ADR-008: Redis Queue Worker and Background Job Architecture

**Date:** 2026-09-06  
**Status:** accepted  
**Supersedes:** None

## Context

The OT-CapEx system operates on factory lines where supervisors and managers must submit and approve timesheets rapidly during tight shift handovers (07:00, 15:00, 23:00 WIB). Synchronous execution of heavier computational workloads—such as recalculating monthly departmental burndown rollups, performing statistical Z-score anomaly scans on every overtime item, and tracking missing physical SPKL document grace periods—would introduce unacceptable HTTP latency and increase the risk of database lock contention.

## Decision

We establish an asynchronous background worker pipeline backed by **Redis queues** (`QUEUE_CONNECTION=redis`).

1. **Scaffolded Base Jobs**:
    - `RunAnomalyDetectionJob`: Scored asynchronously per overtime line item.
    - `RecalculateMonthlyBurnSnapshotJob`: Rollup recalculations for section and departmental snapshots.
    - `SendSpklReminderJob`: Automated alerts for unattached physical SPKL slips before policy grace periods expire.
2. **Resilience and Retries**:
    - Every job explicitly declares `$tries = 3` and an exponential backoff array `$backoff = [30, 120, 300]`.
    - Transient database or external service delays will automatically pause and retry without losing queued payloads.
3. **Environment and Worker Management**:
    - Production environments supervise queue workers using Supervisor daemon processes or Laravel Horizon.

## Consequences

### Positive

- **Near-Zero HTTP Request Latency**: Web submissions and approvals append records immediately and return HTTP 200/redirects within milliseconds.
- **Database Concurrency Isolation**: Burndown rollups read and aggregate committed records in separate worker processes without locking live submission rows.
- **Automatic Fault Recovery**: Failed jobs retry after 30 seconds, 2 minutes, and 5 minutes before entering the `failed_jobs` table for inspection.

### Negative

- **Eventual Consistency**: Dashboard burn snapshots update a few seconds after batch approval rather than instantaneously within the same HTTP lifecycle.

### Neutral

- Requires Redis service running in production and local development environments (fallback to `database` or `sync` driver available during lightweight local testing).
