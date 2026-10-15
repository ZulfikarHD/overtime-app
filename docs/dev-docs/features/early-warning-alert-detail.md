# Early Warning Alert Detail Page (E10-04)

## Overview

E10-04 fills the `early-warning.show` page with the **alert detail**: for one section on one date it shows the alert header (zone badge, index, streak), a burn bar against the department's zone limits, the vitals cards (production volume and manpower change), a 7 working day chart with the Hijau / Kuning / Merah bands, and the follow-ups already linked to the alert (status pill, sent information, switchable history). Everything is computed server-side by `EarlyWarningService::alertDetail()` (E10-01); the page only presents it. A date that is **not an alert** (Hijau, no budget, or in the future) still opens, with a neutral banner. This story does not yet contain the 5W2H form, or the system-written suggestion; those belong to E10-06 and E10-08 (see _Decisions & Trade-offs_). The vitals data now comes from E10-05 ([Early Warning Alert Vitals](./early-warning-alert-vitals.md)).

## Architecture Diagram

```mermaid
flowchart TD
    I[Inbox row or Follow Up] -->|GET /early-warning/section/date| G[Gate viewAlertSection]
    G -->|other department| F403[403]
    G --> V[date_format Y-m-d]
    V -->|invalid| R[302 to inbox + error flash]
    V --> C[EarlyWarningController@show]
    C --> D[EarlyWarningService::alertDetail]
    C --> L[AlertFollowUpService::listForAlert]
    C --> B[inboxUrl: referer or session]
    D -->|alert, series, limits| P[Show.vue]
    L -->|followUps| P
    B -->|backUrl| P
    P --> H[AlertHeaderCard]
    P --> BB[AlertBurnBar]
    P --> VG[AlertVitalsGrid]
    P --> CH[AlertIndexChart + useZoneBandsPlugin]
    P --> FS[AlertFollowUpSummary]
```

## Data Model

No schema change. Follow-ups are read from `alert_follow_ups` by `section_id` + `alert_date` (columns added in E10-01).

Props sent to `early-warning/Show` (all immediate):

| Prop        | Shape                                                                                                                                                           |
| ----------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `section`   | `id`, `name`, `department_id`, `department_name`                                                                                                                |
| `date`      | `Y-m-d` (Asia/Jakarta calendar day)                                                                                                                             |
| `alert`     | The `EarlyWarningService` alert (`zone`, `index_pct`, `streak_days`, limits, `follow_up_status`, `follow_up_id`) or `null` when the date is not an alert        |
| `series`    | Up to 7 working days ending on the alert date: `date`, `index_pct`, `actual_hours`, `zone`, `is_working_day`. Empty when the section has no budget that month   |
| `limits`    | `green_max_pct`, `yellow_max_pct` of the section's department (department override, else plant default)                                                         |
| `vitals`    | `volume_change_pct`, `volume_period_label`, `manpower_change_pct`, `manpower_period_label`. Filled by `EarlyWarningService::vitalsFor()` (E10-05); `null` values show as `N/A`  |
| `followUps` | Follow-ups of this section + date, newest first, shaped by `AlertFollowUpService::toPayload()` (adds `alert_date`, `streak_days`, `sent_by_name`, `created_at`) |
| `backUrl`   | Relative inbox URL including the filters the user came from (`/early-warning` or `/early-warning?...`)                                                          |

## Key Files & UI Mapping

| Layer          | File / Route / Menu                                                       | Purpose                                                                                        |
| -------------- | ------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| Sidebar Menu   | _Early Warning_ → inbox row or **Follow Up** button                       | Entry point (the page is reached from the inbox; no extra sidebar item, no extra route)        |
| Page Component | `resources/js/pages/early-warning/Show.vue`                               | Composes the cards, back link and the "no alert" banner                                        |
| Component      | `resources/js/components/early-warning/AlertHeaderCard.vue`               | Title, date, department, zone badge, index, streak                                             |
| Component      | `resources/js/components/early-warning/AlertBurnBar.vue`                  | Index bar with markers at the green / yellow limits and the "Target 100%" caption              |
| Component      | `resources/js/components/early-warning/AlertVitalsGrid.vue`               | Volume and manpower cards with trend arrow, period, `N/A` + tooltip                            |
| Component      | `resources/js/components/early-warning/AlertIndexChart.vue`               | 7 working day line chart with zone bands, limit lines and the 100% plan line                   |
| Component      | `resources/js/components/early-warning/AlertFollowUpSummary.vue`          | Status pill, sent time and sender, history chips, read-only 5W2H of the selected follow-up     |
| Composable     | `resources/js/composables/useZoneBandsPlugin.ts`                          | Zone-band Chart.js plugin, extracted from the Dashboard chart and shared by both charts        |
| Component      | `resources/js/components/dashboard/DailyIndexTrendChart.vue`              | Now uses `useZoneBandsPlugin` (behaviour unchanged)                                            |
| Types          | `resources/js/lib/earlyWarning.ts`                                        | `SeriesPoint`, `AlertVitals`, `AlertFollowUpItem`, `formatShortDate()`, `formatSignedPct()`    |
| Controller     | `app/Http/Controllers/EarlyWarningController.php` (`show`, `inboxUrl`)    | Authorizes, validates the date, builds the props; `index` remembers the inbox query            |
| Service        | `app/Services/EarlyWarningService.php` (`alertDetail()`)                  | Alert, series and limits (E10-01, unchanged)                                                   |
| Service        | `app/Services/AlertFollowUpService.php` (`listForAlert()`, `toPayload()`) | Eager-loaded follow-ups of one alert; payload now includes sender, streak and created time     |
| i18n           | `lang/id.json`, `lang/en.json`                                            | 12 new keys (kept in sync, sorted)                                                             |
| Tests          | `tests/Feature/EarlyWarningDetailTest.php`                                | 13 tests: props, department limits, non-alert, empty/future, vitals (null and real), follow-ups, back URL |
| Browser tests  | `tests/Browser/EarlyWarning/EarlyWarningDetailBrowserTest.php`            | 5 tests: open from inbox, back link, neutral banner, history chips, phone layout               |

## Flow Explanation

1. **User triggers** — clicks a row or **Follow Up** in the inbox. The inbox request (with its filters) is the Referer of the next request.
2. **Request handling** — `Gate::authorize('viewAlertSection')` runs first (another department = 403, an unknown section = 404 from route binding). An invalid date redirects to the inbox with an `error` flash (after authorization, so it never reveals another department's section).
3. **Business logic** — `alertDetail()` evaluates the section for a 30 day window and returns the alert (or `null`), the series of the last 7 working days (the alert date is always included, even on an HLR day) and the zone limits. `listForAlert()` loads the follow-ups of the section and date.
4. **Back link** — `inboxUrl()` uses the same-origin Referer when it is the inbox path, otherwise the last inbox query stored in the session by `index()`, otherwise the plain inbox. Only the inbox path plus a query string is ever returned.
5. **Response** — `Show.vue` renders the header (only for an alert), the burn bar (when the alert date is in the series), the vitals, the chart and the follow-up summary. Without an alert a neutral banner explains that no follow-up can be created.

## API Endpoints & Routes

| Method | URI                               | Name                 | Controller Action             | Auth                                           |
| ------ | --------------------------------- | -------------------- | ----------------------------- | ---------------------------------------------- |
| GET    | `/early-warning/{section}/{date}` | `early-warning.show` | `EarlyWarningController@show` | auth, verified, role admin/manager/team_leader |

| Response                             | Condition                                                           |
| ------------------------------------ | ------------------------------------------------------------------- |
| 403                                  | Operator, or a non-admin requesting a section of another department |
| 404                                  | Unknown section                                                     |
| 302 to `early-warning.index` + flash | `date` is not a real `Y-m-d` date                                   |
| 200 with `alert: null`               | The date is Hijau, has no budget, or is in the future               |

## Decisions & Trade-offs

- **Scope of this story.** E10-04 consumes three stories that are not built yet. The page delivers everything E10-04 owns and wires it to what exists: vitals were an all-`null` placeholder until E10-05 provided `vitalsFor()` (done; still `N/A`, never a made-up number, when data is missing); the read-only follow-up summary stands in for the 5W2H form until E10-06 / E10-08; the epic's `departments` prop (the "Who" select) is not sent because only the form needs it. See [ADR-043](../decisions/043-early-warning-detail-back-link-and-staged-scope.md).
- **`AlertFollowUpSummary` is an extra component** (not in the epic's inventory). `Show.vue` needs a home for the status block and history; E10-06 adds `FollowUpForm.vue` next to it and can fold it in.
- **Zone band plugin extracted, not copied.** The epic asks for one shared composable; the Dashboard chart now calls it, so both charts paint identical bands.
- **Back link through Referer + session.** Inertia visits send the inbox URL as Referer, which covers the normal case; the session value covers a reload (where the Referer is lost). Both are reduced to the inbox path plus a query string, so there is no open redirect.
- **Neutral colors for vitals.** A volume or manpower increase is information, not good or bad, so the cards use a trend arrow without red/green coloring.
- **Series stops at today.** A future date shows no alert and the series ends today; the page does not invent days.

## Related

- [Early Warning Alert Detection (E10-01)](./early-warning-alert-detection.md)
- [Early Warning Menu, Routes & Authorization (E10-02)](./early-warning-navigation-access.md)
- [Early Warning Inbox Page (E10-03)](./early-warning-inbox.md)
- [Early Warning Alert Vitals (E10-05)](./early-warning-alert-vitals.md)
- [ADR-043](../decisions/043-early-warning-detail-back-link-and-staged-scope.md)
- [Epic-10](../../scrum/Epic-10.md)
