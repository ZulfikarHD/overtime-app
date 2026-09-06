# ADR-012: User Preferences and Display Standards Architecture

**Date:** 2026-09-07  
**Status:** accepted  
**Supersedes:** None

## Context

Users across plant roles (Administrators, Managers, Team Leaders, and Operators) require personal control over their viewing experience (dark mode for night shift environments, light mode for standard daytime office work, and system adaptive mode) as well as operational alert notifications (SPKL physical document pending reminders, budget threshold alerts, and overtime approval updates).

At the same time, corporate manufacturing compliance requires strict consistency in how dates, timestamps, and currencies are presented across all shifts and audits. Allowing individual users to alter timezones or currency formatting would cause severe discrepancies during timesheet auditing and payroll calculations.

## Decision

1. Store user-specific visual and notification preferences directly in a `users.preferences` JSON column with a PHP array cast and a model-level default fallback resolver (`User::getEffectivePreferences()`).
2. Integrate client-side theme selection directly with the `useAppearance` composable, toggling the DOM `<html>` `dark` class instantly without requiring a page reload.
3. Lock plant display standards (Timezone: `WIB / Asia/Jakarta`, Date format: `DD/MM/YYYY`, Currency: `Rupiah Indonesia / Rp`) as immutable company-wide defaults, displayed via a read-only trust signal card on the preferences page.
4. Mount the Preferences interface within the existing `resources/js/layouts/settings/Layout.vue` shell accessible through the user avatar menu, adding 0 primary sidebar navigation items.

## Consequences

### Positive

- **Zero Schema Migrations for Future Flags:** Adding new notification channel toggles or visual options requires only form request validation and Vue updates without additional database migrations.
- **Immediate Visual Responsiveness:** Users experience instant theme switching with smooth CSS transitions while preserving their choice in backend storage.
- **Audit Integrity:** Display standards remain uniform and compliant with factory floor operational practices.
- **Minimal Navigation Footprint:** Adheres strictly to the Epic-02 UX Plan by keeping settings within the user avatar dropdown menu.

### Negative

- **JSON Column Constraints:** In older MySQL/PostgreSQL versions, querying nested JSON properties is slightly slower than querying dedicated relational columns, though individual user preference reads are primary-key lookups.

### Neutral

- Unauthenticated guests default to system appearance until logging into an account with customized settings.
