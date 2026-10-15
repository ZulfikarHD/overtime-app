# Early Warning Inbox Page (E10-03)

## Overview

E10-03 fills the `early-warning.index` page with the **alert inbox**: a filtered, paginated list of the alerts computed by `EarlyWarningService` (E10-01), with a summary strip, zone and follow-up status pills, and a link to the alert detail page. Filters (department, section, date range, zone, follow-up status, page) live in the URL query string and are applied on the server. The heavy part (alerts + summary) is sent as **deferred props**, so the page shell and filter bar render immediately and a pulsing skeleton shows until the data arrives. Non-admin users are locked to their own department on the server, whatever the query string says.

## Architecture Diagram

```mermaid
flowchart TD
    U[User on Early Warning page] -->|changes a filter| V[Index.vue visit]
    V -->|GET /early-warning?date_from=...&zone=red| R[EarlyWarningInboxRequest]
    R -->|invalid| X[302 to default inbox + error flash]
    R -->|valid filters| C[EarlyWarningController@index]
    C --> S1[EarlyWarningService::inboxScope<br/>department lock, section options]
    C -->|immediate props| P[filters, departments, sections]
    C -->|Inertia::defer group inbox| S2[EarlyWarningService::inboxFor]
    S2 --> A[alertsFor<br/>constant number of queries]
    S2 --> F[zone + status filter, summary, pagination]
    P --> Page[Index.vue]
    F -->|deferred reload| Page
    Page --> L[AlertFilterBar / AlertSummaryStrip / AlertList]
    L -->|row or Follow Up| D[early-warning.show]
```

## Data Model

No schema change. The inbox reads alerts from `EarlyWarningService::alertsFor()` and the follow-up status from `alert_follow_ups` (already linked to `section_id` + `alert_date` by E10-01).

Props sent to `early-warning/Index`:

| Prop          | Delivery  | Shape                                                                                                                                |
| ------------- | --------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| `filters`     | immediate | `department_id`, `section_id`, `date_from`, `date_to`, `zone` (`all`/`yellow`/`red`), `status` (`all`/`none`/`draft`/`sent`), `page` |
| `departments` | immediate | Admin: every active department. Others: only their own                                                                               |
| `sections`    | immediate | Sections of the department in scope (empty for an admin without a department filter)                                                 |
| `summary`     | deferred  | `total`, `red`, `yellow`, `without_follow_up`                                                                                        |
| `alerts`      | deferred  | `data` (alerts, 25 per page) and `pagination` (`current_page`, `last_page`, `per_page`, `total`, `from`, `to`)                       |

## Key Files & UI Mapping

| Layer          | File / Route / Menu                                                   | Purpose                                                                                         |
| -------------- | --------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| Sidebar Menu   | _Early Warning_ (`data-test="nav-early-warning"`)                     | Entry point (E10-02)                                                                            |
| Page Component | `resources/js/pages/early-warning/Index.vue`                          | Owns the URL: builds the query string, `router.get`, paging, reset                              |
| Component      | `resources/js/components/early-warning/AlertFilterBar.vue`            | Department / section selects, date range, zone and status pills, reset, date validation         |
| Component      | `resources/js/components/early-warning/AlertSummaryStrip.vue`         | Four KPI cards (`font-mono tabular-nums`), skeleton while deferred                              |
| Component      | `resources/js/components/early-warning/AlertList.vue`                 | Skeleton, empty state, table (md+), stacked cards (below md), pagination                        |
| Component      | `resources/js/components/early-warning/AlertZoneBadge.vue`            | Zone badge from `BURN_ZONE_STYLES` (shared with the detail page)                                |
| Component      | `resources/js/components/early-warning/FollowUpStatusPill.vue`        | Belum / Draft / Terkirim pill (shared with the detail page)                                     |
| Types          | `resources/js/lib/earlyWarning.ts`                                    | Alert, summary, pagination and filter types, `formatAlertDate()`, `MAX_RANGE_DAYS`              |
| Controller     | `app/Http/Controllers/EarlyWarningController.php` (`index`)           | Thin: scope, filters, deferred props                                                            |
| Request        | `app/Http/Requests/EarlyWarningInboxRequest.php`                      | Validates the query string, applies defaults (last 7 days), redirects on invalid input          |
| Service        | `app/Services/EarlyWarningService.php` (`inboxScope()`, `inboxFor()`) | Department lock, filter options, summary, filtering and pagination                              |
| Wayfinder      | `@/routes/early-warning` (`index.url()`, `show.url()`)                | Generated, git-ignored; no Ziggy                                                                |
| i18n           | `lang/id.json`, `lang/en.json`                                        | Labels, hints and error messages (kept in sync, sorted)                                         |
| Tests          | `tests/Feature/EarlyWarningInboxTest.php`                             | 19 tests: deferral, defaults, scope lock, filters, summary, pagination, invalid input           |
| Browser tests  | `tests/Browser/EarlyWarning/EarlyWarningInboxBrowserTest.php`         | 10 tests: admin vs manager, filters and URL, department select, row open, empty, paging, mobile |

## Flow Explanation

1. **User triggers** — opens **Early Warning** from the sidebar or changes a filter. `Index.vue` builds a query string (`date_from`, `date_to` always; `department_id`, `section_id`, `zone`, `status`, `page` only when not default) and calls `router.get(index.url(), query, { preserveState, preserveScroll, replace })`.
2. **Request handling** — `EarlyWarningInboxRequest` authorizes `viewAny`, validates the values and fills the defaults. Any invalid value (unknown zone, impossible date, reversed or longer than 93 days range, page 0) redirects to the plain inbox with an `error` flash instead of breaking the page.
3. **Scope** — `inboxScope()` fixes the department for non-admins (a foreign `department_id` is ignored), returns the filter options and drops a `section_id` that is not in the offered sections.
4. **Deferred data** — `Inertia::render` returns the immediate props; `summary` and `alerts` are two deferred props in the group `inbox`. They share one `inboxFor()` result (memoized in the controller), so the alerts are computed once per request.
5. **List rules** — `inboxFor()` calls `alertsFor()` for the department / section / date range. The **summary** counts that set; the **list** additionally applies the zone and follow-up status filters, keeps the `alertsFor()` order (Merah first, longest streak, newest date), and returns one page of 25. A page past the end falls back to the last page.
6. **Response** — the page shows the skeleton until the deferred reload fills the summary and list; a row, or its **Follow Up** button, opens `early-warning.show` ([E10-04](./early-warning-alert-detail.md)), which restores these filters through its back link.

## API Endpoints & Routes

| Method | URI              | Name                  | Controller Action              | Auth                                           |
| ------ | ---------------- | --------------------- | ------------------------------ | ---------------------------------------------- |
| GET    | `/early-warning` | `early-warning.index` | `EarlyWarningController@index` | auth, verified, role admin/manager/team_leader |

Query parameters (all optional):

| Param           | Values                                          | Default                 |
| --------------- | ----------------------------------------------- | ----------------------- |
| `department_id` | integer (admin only, ignored for others)        | all departments (admin) |
| `section_id`    | integer, must belong to the department in scope | all sections            |
| `date_from`     | `Y-m-d`                                         | `date_to` minus 6 days  |
| `date_to`       | `Y-m-d`                                         | today (Asia/Jakarta)    |
| `zone`          | `all`, `yellow`, `red`                          | `all`                   |
| `status`        | `all`, `none`, `draft`, `sent`                  | `all`                   |
| `page`          | integer ≥ 1                                     | 1                       |

| Response                             | Condition                                                           |
| ------------------------------------ | ------------------------------------------------------------------- |
| 403                                  | Operator, or a non-admin without a role that can see alerts         |
| 302 to `early-warning.index` + flash | Invalid filter value, reversed range, or a range of 93 days or more |

## Decisions & Trade-offs

- **Summary and alerts are both deferred.** The epic lists `summary` as a normal prop, but it comes from the same expensive computation as the list. Making it immediate would block the page on the very work deferral is meant to hide. Both props share one computation. See [ADR-042](../decisions/042-early-warning-inbox-filters-in-url-and-deferred-alerts.md).
- **The summary ignores the zone and status filters.** It describes the department / section / period; the pills narrow only the list. The user keeps the whole picture ("3 alerts, 1 without follow-up") while drilling in.
- **Invalid filters redirect instead of 422.** A hand-edited or outdated shared URL must never leave the user on an error page; the redirect target (the plain inbox) cannot loop.
- **Range limit of 93 days.** Not in the epic; it bounds the per-request scan (streak look-back adds 60 days) while still allowing roughly a quarter of history.
- **Department lock is server-side.** The select is disabled for non-admins for clarity, but the server ignores the parameter regardless.
- **Pills for zone and status, selects for department and section.** Few options, one click, same pattern as the approval queue tabs.
- **Pagination in PHP.** `alertsFor()` already returns every alert of the range in memory; slicing a collection avoids a second query layer. Pages of 25 per the epic.
- **Date format.** `formatAlertDate()` parses and formats as UTC so a `Y-m-d` calendar day never shifts with the browser timezone.

## Related

- [Early Warning Alert Detection (E10-01)](./early-warning-alert-detection.md)
- [Early Warning Menu, Routes & Authorization (E10-02)](./early-warning-navigation-access.md)
- [Early Warning Alert Detail Page (E10-04)](./early-warning-alert-detail.md)
- [ADR-042](../decisions/042-early-warning-inbox-filters-in-url-and-deferred-alerts.md)
- [Epic-10](../../scrum/Epic-10.md)
