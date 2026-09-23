# ADR-038: Excel-Parity Planning Grid UX

**Date:** 2026-09-23
**Status:** accepted

## Context

Factory supervisors historically planned overtime in `planning_ot.xlsx`: dense day × A/B/C/D
cells, keyboard navigation, and a right-hand monitoring block (weekly hours, converted index,
plan vs actual). The first web Planning OT UI used one cell per day plus a modal for categories,
which broke the Excel mental model and hid monitoring.

## Decision

1. Render an editable spreadsheet: sticky NO/NAMA/NPK, four inline inputs per calendar day
   (A/B/C/D), Total Jam on the entry grid.
2. Move Conversi Idx, W1–W5 hours, GT HOUR, and Plan vs Actual index into a **separate monitoring
   panel below** the day grid with tabs (Weekly Hours / Plan vs Actual).
3. Use client-side keyboard navigation (`useSpreadsheetNav`) matching Tab / arrows / Enter.
4. Derive Actual index from approved `overtime_items` for the section/month; Plan index from the
   live grid with HKN×1.5 / HLR×2.0 multipliers.
5. Use Tahun/Bulan selects (not chevron-only month stepping) for period choice.

## Consequences

### Positive

- Matches shopfloor Excel workflow and `planning_ot.xlsx` monitoring semantics.
- Keeps the day grid scannable; summary columns no longer stretch horizontal scroll with entry cells.
- Reuses existing calendar and approval data — no new tables for monitoring.

### Negative

- Dense DOM (~4 × days × employees inputs); acceptable for typical section roster sizes.
- Full page reload on section change (required so `actuals` stay consistent).

### Neutral

- Excel VLOOKUP hour→index tables are intentionally not replicated; flat multipliers match the
  dashboard index charts.
