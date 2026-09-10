# ADR-033: Automotive Login Portal and Responsive Collapsible Sidebar Architecture

**Date:** 2026-09-10  
**Status:** accepted  
**Supersedes:** Extends ADR-001, ADR-007, and ADR-012

## Context

The OT-CapEx system serves PT Isuzu Astra Motor Indonesia's Karawang Assembly Plant. Previously, the application relied on a generic boilerplate Welcome page (`/`) and a standard template login layout, which lacked manufacturing industrial branding, plant operational awareness, and visual harmony with `public/style-guide.html`.

Furthermore, supervisors and line engineers using factory floor tablets and medium-width laptops (`md` viewport breakpoint: 768px – 1023px) experienced excessive horizontal crowding when navigating high-density timesheets and analytical charts because the sidebar remained open by default.

Key requirements addressed in this architecture:

1. **Eliminate Welcome Page**: Replace the generic Welcome page entirely by making the root URL (`/`) render the authenticated system directly (redirecting logged-in users to `/dashboard` and presenting the login portal to unauthenticated users).
2. **Professional Automotive Brand Identity**: Tailor the login screen with ISUZU brand identity (`/isuzu.png`), Karawang Assembly Plant indicators, IATF 16949 / ISO 9001 quality badges, and clean light/white mode contrast with adaptive dark mode.
3. **Sidebar Redesign per Style Guide**: Group navigation into three distinct industrial clusters (_Operasional & Lembur_, _Finansial & Tata Kelola_, _Sistem & Konfigurasi_), integrate a live Plant Telemetry card (Karawang Assembly, 3 Shift / 24 Jam, Ambang Depnaker Maks 14 Jam/Minggu), and display `/isuzu.png` in a single-root Vue component.
4. **Responsive Collapse Ergonomics**: Ensure the sidebar defaults to collapsed (compact icon mode) at the `md` breakpoint (768px – 1023px) and defaults to expanded on full desktop screens (`>= 1024px`), while preserving explicit user manual toggles in cookies.

## Decision

We designed and implemented a unified automotive portal and responsive sidebar navigation architecture:

1. **Unified Root Routing & Welcome Page Deprecation**:
    - Deleted `resources/js/pages/Welcome.vue` and removed the `Welcome` layout branch in `resources/js/app.ts`.
    - Updated `routes/web.php` so root `/` (`route('home')`) checks session authentication: authenticated requests redirect to `/dashboard`, while unauthenticated requests render `auth/Login` directly.
    - Updated `resources/views/app.blade.php` to serve `/isuzu.png` as the browser favicon and apple-touch-icon, with document title defaulting to `ISUZU OT-CapEx`.

2. **Automotive Split Layout (`AuthSplitLayout.vue`)**:
    - Built a split-screen layout with an automotive manufacturing presentation on the left and a glare-free authentication form on the right.
    - **Clean Light Mode Standards**: In light mode, surfaces use crisp white backgrounds (`bg-white`) and slate-50 accents with subtle technical blueprint grid lines (`#e2e8f080`), avoiding heavy dark themes when the user is in light mode. In dark mode, it transitions seamlessly to `#070b12`.
    - **Operational Highlights**: Features three dedicated manufacturing cards:
        - _Alokasi CapEx CIP_: Automatic segregation of fixed asset capitalization hours.
        - _Ergonomi 3-Klik_: Rapid shift submission for Team Leaders and line supervisors.
        - _Batas Depnaker_: Real-time guardrail against exceeding the 14-hour weekly statutory overtime limit.
    - **Telemetry Footer**: Displays live Western Indonesian Time (`WIB`) and active rotational shift status (`Shift 1/2/3`) via `useShiftInfo()`.

3. **Style Guide Compliant Sidebar & Plant Telemetry**:
    - **Single-Root `AppLogo.vue`**: Encloses the `/isuzu.png` asset and plant text within a single root `<div>`, complying with Vue 3 single-root standards.
    - **Modular Navigation**: `NavMain.vue` categorizes items into semantic groups:
        - _Operasional & Lembur_: Dashboard, Input Lembur, Persetujuan Lembur, Laporan Karyawan.
        - _Finansial & Tata Kelola_: Proyek CapEx, Burn Index, Analitik & Keputusan, Budget Planning.
        - _Sistem & Konfigurasi_: Master Data, Administration.
    - **Active Indicator**: Uses ISUZU Red `#cc0000` text, `bg-red-50` (`dark:bg-red-950/40`), and an active red dot indicator.
    - **Plant Telemetry Card**: Embeds real-time plant information (Facility, Shift System, and Depnaker limit) directly into the sidebar above the footer, auto-hiding in collapsed icon mode.
    - **Interactive `SidebarRail.vue`**: Allows border clicking and dragging to toggle sidebar width.

4. **Breakpoint-Aware Responsive Collapse (`SidebarProvider.vue`)**:
    - Uses `@vueuse/core` media queries (`isMd` for `768px – 1023px` and `isDesktop` for `>= 1024px`).
    - Defaults to **collapsed (icon mode)** when viewport is within `md` tablet width.
    - Defaults to **expanded (open)** when viewport is `>= 1024px`.
    - User manual toggle via `SidebarTrigger`, `SidebarRail`, or `Ctrl+B` is persisted via `sidebar_state` cookie (`max-age=7 days`), ensuring manual preference always overrides automatic breakpoint defaults.

## Consequences

### Positive

- **Brand Consistency**: Delivers an official, professional manufacturing aesthetic aligned with PT Isuzu Astra Motor Indonesia and `public/style-guide.html`.
- **Improved Tablet Usability**: Factory supervisors using line tablets in landscape (typically 768px–1023px width) immediately gain 208px of horizontal workspace for tables and charts without manually closing the sidebar.
- **Seamless User Flow**: Eliminates redundant landing page clicks by bringing operators and supervisors directly to the login screen or dashboard.
- **Strict Multilingual Compliance**: Standardizes portal translation keys to clean English phrases, removes hardcoded literals, adds `SetLocale` middleware, and enables real-time language switching (`id` / `en`) via Wayfinder routes in the login portal, sidebar footer, and user menu.

### Negative / Trade-offs

- Initial load on tablet requires users to click icons or the toggle button if they prefer reading full text labels, though tooltips on hover in icon mode mitigate this.
- Cookie state takes precedence over viewport changes once a user manually toggles the sidebar on a given device.

## Related

- [ADR-001: Wayfinder Routing over Ziggy](001-wayfinder-routing-over-ziggy.md)
- [ADR-007: Role-Based Access Control and Scoping](007-role-based-access-control-and-scoping.md)
- [ADR-012: User Preferences and Display Standards](012-user-preferences-and-display-standards.md)
- [Foundation Layout & Wayfinder Navigation](../features/foundation-layout-wayfinder.md)
- [Authentication & Role-Based Access Control](../features/authentication-rbac.md)
