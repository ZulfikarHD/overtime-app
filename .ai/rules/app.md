---
paths:
    - 'app/**/*.php'
---

# App

## date-columns: use whereDate() for cross-driver equality

Eloquent 'date' and 'datetime' casts store values as 'Y-m-d H:i:s' in SQLite (the test DB) but as 'Y-m-d' in MySQL DATE columns. A plain ->where('date_col', '2026-10-01') fails in SQLite because '2026-10-01 00:00:00' != '2026-10-01'. Always use ->whereDate('col', $date) or Carbon::parse($date)->startOfDay() when searching on a cast date column. The same applies to updateOrCreate() where-clause keys: pass Carbon::parse($date)->startOfDay() so both drivers find the existing row.
