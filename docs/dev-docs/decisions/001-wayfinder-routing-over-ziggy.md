# ADR-001: Use Laravel Wayfinder Instead of Ziggy for TypeScript Routing

**Date:** 2026-09-06  
**Status:** accepted  
**Supersedes:** None

## Context

In modern Laravel and Vue 3 Inertia.js applications, frontend components require access to backend routes. The legacy convention utilized Ziggy (`tightenco/ziggy`), which exposed a global JavaScript `route()` helper and injected the entire application route list into the HTML payload.

This approach exhibits several critical flaws:

1. **Lack of Type Safety**: Route names and query/path parameters are unvalidated strings (`route('overtime.items.update', { id: 1 })`), making parameter refactoring error-prone.
2. **Bundle Overhead**: Serializing hundreds of application routes into the client payload leaks internal routes and enlarges initial load sizes.
3. **Developer Experience**: Modern Vue 3 TypeScript workflows benefit from direct auto-complete and compiler errors when routes or controller parameters change.

## Decision

We adopt **Laravel Wayfinder** (`laravel/wayfinder` v0.1.21 and `@laravel/vite-plugin-wayfinder` v0.1.3). Wayfinder automatically inspects Laravel route definitions and generates type-safe TypeScript functions located in:

- `@/actions/` (direct controller actions, e.g., `import { store } from '@/actions/App/Http/Controllers/OvertimeSubmissionController'`)
- `@/routes/` (named route functions)

Legacy Ziggy is strictly prohibited across the entire codebase.

## Consequences

### Positive

- **Compile-Time Type Verification**: Missing parameters, wrong types, or renamed routes fail during `vue-tsc` / `pnpm check`.
- **Tree-Shaking**: Only routes explicitly imported by Vue components are bundled into client assets.
- **Form Helpers**: Wayfinder provides `.form()` and HTTP verbs (`.post()`, `.get()`) that integrate natively with Inertia form helpers.

### Negative

- Requires running the Wayfinder generator (`php artisan wayfinder:generate` or automatic Vite HMR trigger) whenever Laravel route signatures change.

### Neutral

- Developers must import routes as standard ES modules rather than relying on a global window helper.
