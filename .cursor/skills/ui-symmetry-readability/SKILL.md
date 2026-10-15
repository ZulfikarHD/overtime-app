---
name: ui-symmetry-readability
description: Audit and build Vue/Tailwind UI so layouts stay symmetric (aligned rows, equal-width controls, no ragged wrapping) at laptop widths between the md and lg breakpoints, and text stays comfortably readable. Use when creating or reviewing filter bars, toolbars, form rows, card grids, tables, or any page layout; when the user mentions symmetry, alignment, OCD-level polish, broken layout on laptop, breakpoint, responsive, text too small, font size, or UI/UX audit.
---

# UI Symmetry and Readability

The client is highly sensitive to visual symmetry and uses a laptop whose content area falls between the `md` and `lg` breakpoints. They also find text too small. Treat both as hard requirements, not polish.

Project-specific audit numbers and the known offenders are in [reference.md](reference.md).

## Why layouts break in the "md-lg" zone

Tailwind breakpoints look at the **viewport**, but the real space is the **content area**:

- Expanded sidebar is `16rem` (256px). At viewport 1024-1279px, `lg:` is active but content is only about 768-1023px wide.
- Laptops at 125-150% OS scaling, or users zooming the browser to compensate for small text, land in this band.
- `flex flex-wrap` with children of different widths then wraps into rows of different lengths. This is the ragged filter-bar bug.

Design for the **content width**, not the viewport.

## Rule 1: Symmetry

1. **Control groups are grids, never `flex-wrap`.** Filters, toolbars, and form rows use CSS grid so every control in a row has the same width and rows fill edge to edge.
2. **Size columns by container, not viewport.** Mark the page or card wrapper `@container` and use `@md:` / `@2xl:` / `@4xl:` variants. Use `sm:`/`lg:` only for things tied to the real viewport (sidebar, drawers).
3. **Column count must divide the item count.** 4 controls: 1, 2, or 4 columns. 3 controls: 1 or 3 columns. If it cannot divide, make the last item `col-span-full` (or spanning the remainder) so no orphan row is shorter than the rest.
4. **One height, one gap, one text size per bar.** Inputs, selects, date pickers, and buttons in the same bar share the same `h-*`, `text-*`, and `gap-*`. Icon-only buttons are square at the same height (`size-9`).
5. **Controls fill their cell.** `w-full min-w-0` on every control. Never `sm:w-auto` or `sm:max-w-52` on a sibling of equal-weight controls, because intrinsic widths of `<select>` and `<input type="date">` differ by browser.
6. **Decorative icons stay out of the grid.** A lone filter icon in the first cell shifts only that row. Put it in the bar heading, or drop it.
7. **Actions align to an edge.** Primary and reset buttons sit in their own end-aligned slot (`justify-end`), not mid-row.
8. **Same label strategy across a bar.** All controls have a label above, or none do.

```vue
<!-- BAD: ragged rows, unequal widths, orphan icon (the reported bug) -->
<div class="flex flex-wrap items-center gap-2 lg:max-w-[28rem]">
    <Filter class="size-3.5" />
    <select class="h-8 sm:w-auto text-xs">...</select>
    <select class="h-8 sm:max-w-52 text-xs">...</select>
    <input type="date" class="h-8 sm:w-auto text-xs" />
    <button class="h-8">...</button>
</div>

<!-- GOOD: equal tracks, driven by container width -->
<div class="@container w-full">
    <div class="grid grid-cols-1 gap-3 @md:grid-cols-2 @3xl:grid-cols-[repeat(3,minmax(0,1fr))_auto]">
        <select class="h-9 w-full min-w-0 text-sm">...</select>
        <select class="h-9 w-full min-w-0 text-sm">...</select>
        <input type="date" class="h-9 w-full min-w-0 text-sm" />
        <button class="size-9 justify-self-end">...</button>
    </div>
</div>
```

Cards and KPI grids follow the same rule: equal-height cards (`grid` + `h-full` children), identical padding, values right-aligned with `tabular-nums`. Do not let one card have a badge row that makes it taller than its neighbours; reserve the row or move the badge inline.

## Rule 2: Readable text

Calibri is the app font (client request). It renders about 8-10% smaller than Segoe UI or Arial at the same px, so size floors are one step higher than usual.

| Use | Minimum |
| --- | --- |
| Body, table cells, form controls, select, buttons, tabs | `text-sm` (14px) |
| Table header, badge, caption, helper text, chart axis | `text-xs` (12px) |
| Page title / section title | `text-xl` / `text-base font-semibold` or larger |
| KPI value | `text-2xl` or larger, `font-mono tabular-nums` |

Hard bans:

- No arbitrary pixel text sizes: `text-[8px]`, `text-[9px]`, `text-[10px]`, `text-[11px]`. Use `text-xs` or larger.
- No `text-xs` on interactive controls or on the only line of a table cell.
- Do not shrink text to make a row fit. Reduce columns, truncate with a tooltip, or move to a second row of the grid.
- Muted text keeps contrast: `text-muted-foreground` or `text-slate-600` minimum on white, never `text-slate-400` for information.
- Touch and click targets: at least `h-9` (36px). Prefer `h-10` for primary actions on laptops.
- Never convert to larger text by inflating one component. Change the shared token or UI primitive (`components/ui/*`) so every page changes together.

## Workflow: audit a page

Copy and track:

```
- [ ] 1. Render the page at 1024, 1152, 1280, 1366 viewport widths (sidebar expanded) plus 768 (sidebar collapsed)
- [ ] 2. Find flex-wrap control groups: rg 'flex-wrap' on the page and its child components
- [ ] 3. Find sub-12px text: rg 'text-\[(8|9|10|11)px\]'
- [ ] 4. Find mixed control heights/text sizes in the same bar
- [ ] 5. Fix with grid + container queries, shared tokens, and h/text consistency
- [ ] 6. Re-render at the same widths and confirm left and right edges of every row align
- [ ] 7. Add or update a browser test that resizes to the widths above
- [ ] 8. Run pnpm lint and pnpm build
```

Edges aligned means: for each row, the first control starts at the same x and the last control ends at the same x as the other rows, and heights match within 1px.

## Browser test pattern

Browser tests live in `tests/Browser/`. Use `resize()` and assert geometry via script instead of screenshots.

```php
it('keeps dashboard filter controls symmetric between md and lg', function (int $width) {
    $user = User::factory()->admin()->create();

    $page = visit('/dashboard')->actingAs($user)->resize($width, 800);

    $page->assertScript(<<<'JS'
        (() => {
            const bar = document.querySelector('[data-test="dashboard-filter-bar"]');
            const items = [...bar.querySelectorAll('select, input')];
            const heights = new Set(items.map(el => Math.round(el.getBoundingClientRect().height)));
            const lefts = [...new Set(items.map(el => Math.round(el.getBoundingClientRect().left)))];
            const rights = [...new Set(items.map(el => Math.round(el.getBoundingClientRect().right)))];
            return heights.size === 1 && lefts.length <= 2 && rights.length <= 2;
        })()
    JS, true);
})->with([1024, 1152, 1280, 1366, 768]);
```

Adjust the `lefts` / `rights` limits to the number of columns at each width. If the Playwright harness hangs, report it and ask the user to run the browser test locally instead of retrying.

## Do not

- Do not fix symmetry per page with magic widths (`w-[13.5rem]`). Use grid tracks.
- Do not add `overflow-x-auto` to hide a layout that does not fit.
- Do not use emoji or glyph separators for alignment; follow the no-emoji rule.
- Do not apply this by editing only one page. Search for the same pattern and fix siblings, or list them.

Combine with `isuzu-frontend-development` (visual tokens, CapEx/OpEx colors, tabular figures) and `tailwindcss-development` (v4 syntax, container queries).
