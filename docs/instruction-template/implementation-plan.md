# Task: Implement [E05-06] From Scrum Plan, Test, and Document

## Step 0 — Read the UI/UX Plan (mandatory if it exists)

Before reading the scrum file, check if a UX plan exists for this epic.

**How to find it:** The UX plan is named `Epic-[N]-ux-plan.md` in the same directory as the scrum file.
Example: if the scrum file is `@docs/scrum/Epic-[N].md`, look for `@docs/scrum/Epic-[N]-ux-plan.md`.

1. If the UX plan exists — **read it fully first**, before the scrum file or anything else.
2. The UX plan is a **hard constraint**, not a suggestion. It defines:
    - How many sidebar items and routes this epic creates (usually fewer than you'd naturally split it into)
    - Which sub-epics live in the same UI surface (tab, drawer, panel) instead of separate pages
    - What must NOT be split into its own route or navigation item
    - User journey step limits and Indonesian UX guardrails
3. Keep the **Navigation Footprint** and **Implementation Boundaries** sections open in your context throughout implementation. Every time you are about to create a new page, route, sidebar item, or modal — cross-check it against the UX plan first.
4. If no UX plan exists: proceed, but default to the most conservative UI surface choices — prefer combining sub-epics into fewer pages over splitting them. Note in your output that no UX plan was found.

---

## Step 1 — Read the source of truth

1. Open and fully read `[@docs/scrum/Epic-05.md ]`.
2. Find the section for **[E05-06]**. Quote or restate its full scope/requirements in your own words before continuing — including acceptance criteria, edge cases, and any explicitly out-of-scope items mentioned in the plan.
3. Do not rely on memory or assumptions about what this feature is — read the file directly.
4. If the plan references other features, files, or docs this feature depends on, open and read those too before continuing.

## Step 2 — Survey the existing codebase before writing anything

1. Search the codebase for existing patterns, components, utils, or modules this feature should reuse or follow the conventions of. Do not invent new patterns if an established one already exists.
2. Identify every file that will need to be created or modified. List them explicitly with a one-line reason for each.
3. Identify every other feature/module that shares state, data, components, or APIs with what you're about to build. List these now — you will re-check them in Step 6.
4. Do not write code yet. Show this survey as output first.

## Step 3 — Implement

1. Implement the feature to match the plan from Step 1 exactly. If you must deviate from the plan for a technical reason, state the deviation explicitly and why it's necessary — do not deviate silently.
2. Handle edge cases proactively: null/undefined inputs, empty states, loading states, error states, race conditions, and permission/auth checks where relevant. Do not implement only the happy path.
3. Match the codebase's existing conventions (naming, file structure, error handling patterns, styling approach) — do not introduce a new convention without a stated reason.
4. After implementation, re-read every file you changed or created to confirm the code is actually there as intended — don't just assume the edit worked.

## Step 4 — Self bug-check (mandatory, not optional)

This app has a history of bugs from initial development — do not assume your first pass is correct.

1. Re-review your own new code specifically for: logic errors, edge cases, null/undefined handling, race conditions, incorrect state updates, off-by-one errors, and broken error handling.
2. Actively try to break your own implementation mentally before concluding it's solid — list at least the edge cases you specifically checked.
3. Fix anything you find. Do not move to Step 5 with known unfixed issues unless you explicitly justify deferring them.

## Step 5 — Full Playwright OR PEST (with browser tools) regression test (mandatory, not optional)

This step exists to confirm the new feature works end-to-end and that it did not break anything else. Do not skip it and do not run a partial/scoped test only.

1. Write Playwright tests covering the new feature's core flows and acceptance criteria from Step 1, including at least one failure/edge-case scenario, not just the happy path.
2. Run the **full** Playwright test suite for the app — not just the new tests. Cross-feature regressions are the whole point of this step.
3. Report full results: total tests run, passed, failed, and skipped. Do not summarize as "tests mostly pass" — list every failure by name.
4. For every failure: determine if it's a **pre-existing failure unrelated to this work**, a **regression caused by your new code**, or **a real bug in the new feature**. Fix regressions and real bugs, then re-run the full suite again.
5. Repeat this step until the full suite passes cleanly, or until every remaining failure is explicitly documented as pre-existing/out-of-scope with justification.

## Step 6 — Cross-feature impact check (mandatory)

1. Revisit the list of related features/modules from Step 2.
2. For each one, explicitly check whether the new implementation could have broken it, using both code review and the Step 5 test results as evidence.
3. Prioritize and clearly flag the **most critical** cross-feature risks at the top of this section — don't bury them.

## Step 7 — Update documentation

1. Run the `/create-documentation` command/workflow.
2. Follow **every single step of its protocol** — do not shortcut, skip, or paraphrase steps in that protocol.
3. Confirm explicitly, step by step, that each part of the `/create-documentation` protocol was completed.

## Output format

Structure your final response with these exact headers, in this order:

1. **UX Plan Compliance** (was a UX plan found? which boundaries were applied? any deviations from the UX plan and why?)
2. **[E05-06] Plan Summary**
3. **Codebase Survey** (files touched + related features identified)
4. **Implementation Summary** (what was built, any deviations from plan + why)
5. **Self Bug-Check Findings** (edge cases checked, issues found and fixed)
6. **Playwright Full Test Results** (pass/fail counts + every failure explained)
7. **Cross-Feature Impact** (most critical first)
8. **Documentation Update Confirmation**

Do not skip any header, even if a section is empty — write "None found" explicitly rather than omitting it.
