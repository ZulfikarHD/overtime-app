---
paths:
    - 'app/**/*.php'
---

# App

## date-columns: use whereDate() for cross-driver equality

Eloquent 'date' and 'datetime' casts store values as 'Y-m-d H:i:s' in SQLite (the test DB) but as 'Y-m-d' in MySQL DATE columns. A plain ->where('date_col', '2026-10-01') fails in SQLite because '2026-10-01 00:00:00' != '2026-10-01'. Always use ->whereDate('col', $date) or Carbon::parse($date)->startOfDay() when searching on a cast date column. The same applies to updateOrCreate() where-clause keys: pass Carbon::parse($date)->startOfDay() so both drivers find the existing row.

## sql-dialect: never use SQLite-only functions in app/seeder queries

Production is MySQL/MariaDB. Never use `strftime()`, `CAST(... AS REAL)`, `julianday()`, `datetime('now')`, or other SQLite-only SQL in Controllers, Services, Actions, Models, or Seeders — even if PHPUnit uses SQLite. Prefer Eloquent (`whereYear`/`whereMonth`/`whereDate`/`whereBetween`) or portable SQL (`DATE(col)`, `CAST(... AS DECIMAL(10,2))`, `COALESCE`, `CASE`).
