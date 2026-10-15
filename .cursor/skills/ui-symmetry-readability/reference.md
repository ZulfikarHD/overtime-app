# Audit Baseline (Oct 2026)

Measured over `resources/js` with ripgrep. Re-run the commands to track progress.

## Findings

| # | Finding | Evidence | Severity |
| --- | --- | --- | --- |
| 1 | Sub-12px text is widespread | `text-[11px]` 362, `text-[10px]` 328, `text-[9px]` 17, `text-[8px]` 2 | High |
| 2 | `text-xs` is the default body size | `text-xs` 1202 vs `text-sm` 310 | High |
| 3 | Calibri shrinks everything further | `--font-sans` starts with `Calibri` in `resources/css/app.css` | High |
| 4 | Control groups use `flex-wrap` | 73 files contain `flex-wrap`; 13 files have a filter `data-test` hook (candidate filter bars) | High |
| 5 | Viewport breakpoints ignore the sidebar | Sidebar `16rem`; only 4 KPI cards + `CardHeader` use container queries | High |
| 6 | Mixed control sizes | `h-7` 59, `h-8` 97, `h-9` 91 across pages, with `ui/*` defaulting to `h-9` | Medium |
| 7 | Native `<select>` mixed with `ui/Select` | 21 files with raw `<select>` | Medium |
| 8 | Content area capped at `max-w-7xl` | `components/AppContent.vue` | Low |

## Root cause of the reported screenshot

`resources/js/pages/Dashboard.vue` filter bar (`data-test="dashboard-filter-bar"`):

- Wrapper has `lg:w-auto lg:max-w-[min(100%,28rem)]` which forces a 448px box at the `lg` breakpoint.
- Children are `flex-wrap` with `sm:w-auto` / `sm:max-w-52`, so the first select, second select, date, and reset button have different widths.
- The filter icon is inside the first select's wrapper, so row 1 is offset from row 2.
- Result: two rows, different widths, different left edges.

The same pattern is in `components/dashboard/DailyBurnLineChart.vue` (section selector at the card header).

## Why the md-lg zone

| Viewport | Sidebar | Content width (approx.) | Active Tailwind breakpoint |
| --- | --- | --- | --- |
| 768-1023 | collapsed to icons (`3rem`) | 720-975px | `md` |
| 1024-1279 | expanded (`16rem`) | 768-1023px | `lg` (misleading) |
| 1280-1535 | expanded | 1024-1279px | `xl` |

A 1920x1080 laptop at 150% scale is 1280 CSS px; at 175% it is about 1097; at 200% it is 960. Browser zoom to enlarge text moves users down this table.

## Re-run commands

```bash
cd resources/js
rg -o 'text-\[(\d+)px\]' -r '$1' --no-filename | sort | uniq -c | sort -rn
rg -c 'flex-wrap' --glob '*.vue' | wc -l
rg -l '<select' --glob '*.vue'
rg -l '@container' --glob '*.vue'
```

## Suggested rollout order

1. Shared primitives: `components/ui/input`, `select`, `button` sizes and a shared filter-bar component so every page gets symmetry for free.
2. Replace `text-[Npx]` with `text-xs` and bump table body and controls to `text-sm`. Start with pages used daily: `Dashboard.vue`, `overtime/Index.vue`, `overtime/ApprovalQueue.vue`, `overtime/Create.vue`.
3. Convert the filter bars (start with the 13 files that expose a filter `data-test`) to the grid pattern.
4. Wrap `AppContent` children in a container query context so `@md:` variants work everywhere.
5. Consider a root text scale (for example `html { font-size: 106.25% }`) only after step 2, since rem-based utilities will scale but arbitrary px values will not.

Decisions needed from the client or product owner: whether to offer a user-level "text size" preference in `pages/settings/Preferences.vue`, and whether to keep Calibri.
