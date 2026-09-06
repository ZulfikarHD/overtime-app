# Prompt: Generate UI/UX Plan for Epic

## Usage

Use this prompt before starting any implementation. Provide the epic file as context.

**Example command:**

> "Create the UX plan for E23 using @scrum-planning/extended/E23-contact-profile-enrichment.md"

Output is saved in the same folder as the epic:
`@docs/scrum/Epic-[N]-ux-plan.md` in english NOT indonesian

---

## Context

Target users are:

- Indonesian non-tech-savvy UMKM owners and business operators
- Supervisors and customer service agents
- Smartphone-primary users, not developers
- **Low patience** — they abandon a flow if it requires too many steps
- **Mid-level digital literacy** — comfortable with WhatsApp, unfamiliar with complex SaaS

---

## Your Task

Read the provided epic file and produce a structured UI/UX plan document.
**Do not write code.** This plan is the hard constraint the implementing AI must follow.

---

## Step 1 — Read & Understand the Epic

1. Open and fully read the provided epic file.
2. Identify all sub-epics and user stories.
3. Group them by: **what the user actually sees** vs. **what is purely backend/system logic** (no dedicated UI needed).
4. Note which sub-epics are already done (if the epic has a progress checklist).

---

## Step 2 — Navigation Footprint Decision

Decide the minimal navigation footprint for the **entire epic**:

**Default rules (follow these unless there is a strong reason not to):**

- Prefer **1 page** over 2 separate pages

Answer these questions:

- How many sidebar items does this epic require (0, 1, or more)?
- How many distinct routes/pages does this epic need?
- Which sub-epics can share one page (via tabs or panels)?
- Which sub-epics are better as a drawer/sheet rather than a full page?
- Which sub-epics require no UI at all (pure backend logic)?

---

## Step 3 — Screen Inventory

List every distinct screen, panel, or modal that will exist. For each:

| Screen / Panel | Location | What's visible on first open | Further triggers | Click depth |
| -------------- | -------- | ---------------------------- | ---------------- | ----------- |

- **Location**: route (`/contacts`) or position (`right panel inside /inbox`)
- **First open**: what the user sees without any interaction
- **Further triggers**: buttons, drawers, modals that appear after interaction
- **Click depth**: how many clicks from the epic's main entry point to reach this

---

## Step 4 — User Journey Maps

For each **primary user goal** in this epic, write a numbered step-by-step journey:

```
Goal: [name of goal, e.g. "Save a new contact from inbox"]
Starts at: [where the user is when they begin]
Steps:
  1. ...
  2. ...
  3. ...
Done: [what "finished" looks like for the user]
Step count: N
Status: ✅ OK (≤4) / ⚠️ Needs simplifying (5-6) / ❌ Too long (>6)
```

If a frequently-used task exceeds 4 steps, **you must** write a simplification proposal.

---

## Step 5 — Indonesian UX Guardrails

Write specific guardrails for this epic based on the target user:

### Patience Thresholds

- Tasks done **every day** (e.g. reply to chat, view contact): max **2–3 steps**
- Tasks done **occasionally** (e.g. create a new tag, merge contacts): max **4–5 steps**
- Tasks done **rarely** (e.g. initial setup): more steps are acceptable, but must have clear guidance

### Cognitive Load Budget

- Maximum **3–4 distinct actions** visible on screen at once
- Forms: maximum **3–4 fields** visible before scrolling
- Don't show advanced options until the user needs them (progressive disclosure)
- No modals inside modals (max 1 modal depth)

### Trust Signals

- Destructive actions (delete, merge, archive): **must** have a confirmation with a plain-language consequence description
- After a successful action: **must** show immediate visual feedback (toast/notification), not just a silent redirect
- Error messages: use friendly language with a suggested action — no error codes or technical terms

### Risks Specific to This Epic

Identify which sub-epics or features risk violating the guardrails above, and write mitigations:

| Sub-epic | Risk | Mitigation |
| -------- | ---- | ---------- |

---

## Step 6 — Implementation Boundaries (Anti-Splitting Rules)

This section tells the implementing AI what **NOT to do**:

### Do Not Split — Combine Into One Surface:

- [list sub-epics that must share one page/panel, not be split into separate pages]

### Make a Tab, Not a New Route:

- [list content that should be a tab, not a page like `/contacts/notes`]

### Make a Drawer/Sheet, Not a Full Page:

- [list content that should be a slide-in panel, not a full page]

### Backend-Only — No Dedicated UI:

- [list sub-epics that are pure system logic, no page or menu needed]

### Strictly Forbidden:

- Do NOT create a sidebar item for [X] — it belongs inside [Y]
- Do NOT create a route `/[path]/[sub-path]` for [X] — use [existing surface] instead

---

## Output Format

Save the document as `scrum-planning/[phase]/E{N}-ux-plan.md`.

Use exactly this structure:

```markdown
# UX Plan — E{N}: [Epic Name]

> Created before implementation. This document is a hard constraint for all work in Epic E{N}.
> Epic file: `scrum-planning/[phase]/E{N}-[slug].md`

## 1. Navigation Footprint

[content]

## 2. Screen Inventory

[content]

## 3. User Journey Maps

[content]

## 4. Indonesian UX Guardrails

[content]

## 5. Implementation Boundaries

[content]
```
