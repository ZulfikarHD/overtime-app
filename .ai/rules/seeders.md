---
paths:
  - 'app/**/*.php,database/seeders/**/*.php'
---

# Seeders

## No SQLite-only SQL in runtime or seeders
Production is MySQL/MariaDB. Never use strftime(), julianday(), CAST(... AS REAL), or other SQLite-only functions in controllers, actions, models, or seeders. Prefer Eloquent whereYear/whereMonth/whereDate/whereBetween, or portable DATE() / CAST(... AS DECIMAL). PHPUnit SQLite is a test harness only — never write SQLite dialect so tests pass.
