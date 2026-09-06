# ADR-003: Non-Blocking SPKL Document Attachment with Policy Grace Periods

**Date:** 2026-09-06  
**Status:** accepted  
**Supersedes:** None

## Context

Under Indonesian manufacturing labor regulations and internal plant policy, overtime work must be authorized by a formal _Surat Perintah Kerja Lembur_ (SPKL) document. In legacy paper-based workflows, shift supervisors frequently withheld entering timesheets until paper SPKL forms were printed, physically stamped, and scanned.

This created critical operational failures:

- Delays of 2–5 days before plant managers had visibility into daily overtime burn.
- Shift handovers at 07:00, 15:00, and 23:00 WIB were stalled by paperwork bottlenecks.
- Timesheet data was entered retrospectively from memory, causing severe accuracy errors.

## Decision

We decouple shift recording from document verification:

1. **Immediate Non-Blocking Submission**: Team Leaders submit actual shift hours immediately at shift conclusion. No attached file is required to complete submission.
2. **Independent SPKL State Machine**: Each submission automatically initializes a child `spkl_documents` record with state `PENDING`.
3. **Configurable Grace Period**: Plant or department policy defines `policy_thresholds.spkl_grace_period_days` (default: 2 business days). The `due_date` is computed excluding Sundays and public holidays (`HLR`).
4. **Passive Reminders & Badges**: Records without attached SPKLs display a prominent UI badge (`Belum Ada Lampiran`) and appear on the Team Leader's pending action tray.
5. **Post-Attachment Transition**: Once the PDF or physical document scan is uploaded, the state transitions to `ATTACHED` and subsequently `VERIFIED` by administration.

## Consequences

### Positive

- **Zero Shift Bottlenecks**: Supervisors record worker hours in under 3 minutes at shift handover.
- **Real-Time Operational Visibility**: Plant management views actual overtime burn on the same day the shift was worked.
- **Clear Accountability**: The system tracks unattached documents through automated grace period escalation jobs.

### Negative

- Requires a secondary audit step to ensure Team Leaders do not abandon pending SPKL attachments after the shift is logged.
- The approval workflow must visually distinguish between items with verified SPKLs and items pending attachment.

### Neutral

- Digital file storage must support both image captures (smartphones/tablets on the factory floor) and formal PDF scans.
