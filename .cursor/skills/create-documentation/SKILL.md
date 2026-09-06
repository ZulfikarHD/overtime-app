---
name: create-documentation
description: Generate comprehensive project documentation in docs/ folder. Covers dev-docs (architecture diagrams, API specs, feature explanations, decision records) and user-docs (end-user guides). Use when creating documentation, writing feature docs, explaining system design, recording architectural decisions, writing user guides, or when the user mentions docs, documentation, ADR, user guide, or API docs.
---

# Create Documentation

## Overview

Generate two categories of documentation stored in `docs/` with clear folder structure:

```
docs/
├── Readme.md                    # Index of all documentation
├── architecture.md              # Existing - system architecture
├── dev-docs/
│   ├── README.md                # Dev docs index
│   ├── features/
│   │   └── [feature-name].md   # Per-feature technical docs
│   ├── api/
│   │   └── [endpoint-group].md # API endpoint documentation
│   └── decisions/
│       └── [NNN]-[title].md    # Architecture Decision Records
└── user-docs/
    ├── README.md                # User docs index
    └── guides/
        └── [feature-name].md   # Per-feature user guide
```

---

## Dev Docs

### Feature Documentation (`docs/dev-docs/features/[feature-name].md`)

Dev docs are written for engineers and system maintainers. They must document both technical implementations (URL endpoints, controllers, models) AND map them to the corresponding UI sidebar navigation / pages.

Each feature doc MUST include:

```markdown
# [Feature Name]

## Overview

One-paragraph summary of what this feature does and why it exists.

## Architecture Diagram

\`\`\`mermaid
flowchart TD
A[User Action] --> B[Controller]
B --> C[Service/Model]
C --> D[Database]
\`\`\`

## Data Model

\`\`\`mermaid
erDiagram
ORDER ||--o{ ORDER_ITEM : contains
ORDER_ITEM }o--|| MENU_ITEM : references
\`\`\`

## Key Files & UI Mapping

| Layer          | File / Route / Menu                           | Purpose                |
| -------------- | --------------------------------------------- | ---------------------- |
| Sidebar Menu   | `Overtime Entry` / `Verification & Approvals` | User entry point in UI |
| Page Component | `resources/js/pages/X/Index.vue`              | Vue page component     |
| Controller     | `app/Http/Controllers/XController.php`        | Handles requests       |
| Model          | `app/Models/X.php`                            | Eloquent model         |
| Request        | `app/Http/Requests/XRequest.php`              | Validation             |

## Flow Explanation

Step-by-step explanation of how data flows through the system for this feature.

1. **User triggers** — describe the UI action (which sidebar item or button is clicked)
2. **Request handling** — controller + validation + route
3. **Business logic** — what happens with the data
4. **Response** — what gets returned/rendered

## API Endpoints & Routes (if applicable)

| Method | URI                  | Controller Action       | Purpose      | Auth           |
| ------ | -------------------- | ----------------------- | ------------ | -------------- |
| GET    | `/t/{tenant}/orders` | `OrderController@index` | List orders  | auth, verified |
| POST   | `/t/{tenant}/orders` | `OrderController@store` | Create order | auth, verified |

## Decisions & Trade-offs

Document WHY certain approaches were chosen:

- Why X library over Y?
- Why this data structure?
- What constraints influenced the design?

## Related

- Links to related features, ADRs, or external resources
```

### API Documentation (`docs/dev-docs/api/[endpoint-group].md`)

```markdown
# [Endpoint Group] API

## Base URL

`/api/v1/[resource]`

## Authentication

Bearer token via Laravel Sanctum

## Endpoints

### GET /api/v1/[resource]

**Description:** Brief description

**Query Parameters:**

| Param | Type | Required | Default | Description |
| ----- | ---- | -------- | ------- | ----------- |
| page  | int  | No       | 1       | Page number |

**Response 200:**

\`\`\`json
{
"data": [...],
"meta": { "current_page": 1, "total": 50 }
}
\`\`\`

**Error Responses:**

| Code | Description     |
| ---- | --------------- |
| 401  | Unauthenticated |
| 403  | Unauthorized    |
```

### Architecture Decision Records (`docs/dev-docs/decisions/[NNN]-[title].md`)

Number sequentially (001, 002, etc.). Format:

```markdown
# ADR-[NNN]: [Title]

**Date:** YYYY-MM-DD
**Status:** accepted | superseded | deprecated
**Supersedes:** ADR-XXX (if applicable)

## Context

What is the issue? What forces are at play?

## Decision

What is the change we're making?

## Consequences

### Positive

- Benefit 1
- Benefit 2

### Negative

- Trade-off 1
- Trade-off 2

### Neutral

- Side effect that is neither good nor bad
```

---

## User Docs

### User Guide (`docs/user-docs/guides/[feature-name].md`)

Written for end-users (supervisors, managers, HR admins, team leaders). Use clear, accessible English with no technical jargon or raw URL paths.

> **CRITICAL GATE: Navigation Names by Sidebar**  
> Always refer to UI navigation using the exact **sidebar display name** in English as shown in the UI, NEVER raw URL paths (`/overtime/create`, `/approvals`) or English technical/code jargon.
>
> - **Overtime Entry** (not `/overtime/create` or `StoreOvertimeSubmission`)
> - **Verification & Approval** (not `/approvals` or `VerificationController`)
> - **Budget Burn Index** (not `/analytics/burn` or `BurnIndex`)
> - **Employee Welfare** (not `/reports/welfare`)
> - **Settings → Profile / Security / Appearance**

```markdown
# [Feature Name] - User Guide

## What is [Feature]?

Brief explanation in plain, easy-to-understand English.

## How to Use

### [Step / Action 1]

1. Open the **[Sidebar Menu Name]** menu in the sidebar
2. Click the **[Button Name]** button
3. Fill in the form fields:
    - **[Field 1]**: explanation
    - **[Field 2]**: explanation
4. Click **Save** (or **Submit**)

> 💡 **Tip:** Helpful tip for the user.

### [Step / Action 2]

...

## Frequently Asked Questions (FAQ)

**Q: [Common question]?**
A: [Clear and concise answer]

## Troubleshooting

| Issue                 | Solution           |
| --------------------- | ------------------ |
| [Problem description] | [Resolution steps] |
```

---

## Workflow

When creating documentation:

1. **Identify scope** — Which feature/module to document?
2. **Explore the code** — Read controllers, models, routes, Vue pages
3. **Create dev-docs** — Technical explanation with diagrams in English
4. **Create user-docs** — End-user guide in English
5. **Update indexes** — Add entry to README files
6. **Record decisions** — If architectural choices were made, create ADR

## Diagram Guidelines (Mermaid)

Use appropriate diagram types:

| Purpose             | Mermaid Type                        |
| ------------------- | ----------------------------------- |
| Request/data flow   | `flowchart TD` or `sequenceDiagram` |
| Data relationships  | `erDiagram`                         |
| State transitions   | `stateDiagram-v2`                   |
| User journey        | `journey`                           |
| Component structure | `graph TD`                          |

## Writing Style

All documentation (Dev docs, User docs, API specs, and ADRs) MUST be written in **English**.

| Audience  | Language                 | Tone                     |
| --------- | ------------------------ | ------------------------ |
| Dev docs  | English (technical)      | Precise, reference-style |
| User docs | English (plain language) | Friendly, step-by-step   |
| ADRs      | English                  | Concise, factual         |

## Index Updates

After creating any doc, update the relevant README:

- `docs/Readme.md` — master index
- `docs/dev-docs/README.md` — dev docs index
- `docs/user-docs/README.md` — user docs index
