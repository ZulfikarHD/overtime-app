---
paths:
    - 'app/Actions/Overtime/**'
---

# Overtime

## SPL importer reads section/dept from header row

ImportSplExcelAction reads the sheet-level dept name from row 6 col H and section name from row 7 col H. Both stored as section_name_snapshot/department_name_snapshot on spl_entries. IDs resolved via a case-insensitive name map built once per execute(). Use section_name_snapshot (not the FK relation) for display — FK may be null if section not yet in DB.
