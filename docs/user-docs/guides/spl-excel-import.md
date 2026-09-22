# Input Lembur (SPL Upload) — User Guide

## What is Input Lembur?

Input Lembur allows administrators and managers to upload the factory's standard SPL Excel file
(`spl_manual_ot.xlsx`) into the system. The system reads each sheet (named after the day of the
month), extracts each employee's overtime session, and stores it in the database.

If you re-upload the same file or an updated version, the system **updates** existing records
instead of creating duplicates — matching on employee NPK, date, and start time.

---

## Who Can Use This?

| Role                       | What they can do                                |
| -------------------------- | ----------------------------------------------- |
| **Administrator**          | Upload files, view all entries, delete entries  |
| **Manager**                | Upload files, view entries for their department |
| **Team Leader / Operator** | No access                                       |

---

## How to Use

### Uploading an SPL File

1. Open **Input Lembur (SPL)** in the left sidebar.
2. Select the **Year** and **Month** that the SPL file covers.
3. Either drag and drop your `spl_manual_ot.xlsx` file onto the upload zone, or click the zone
   to browse for the file.
4. Click **Upload & Import**.
5. A summary banner will show how many rows were **added** and how many were **updated**.

> 💡 **Tip:** The file must be in `.xlsx` or `.xls` format and no larger than 10 MB. Only files
> where sheet names are numbers (1–31) will be processed.

### Viewing Imported Entries

The table below the upload form shows all SPL entries that match the currently selected year
and month filter. Each row shows:

- Employee name and NPK
- Date and day type (HKN/HLR)
- Start and end times
- Total hours
- Job type and OT code
- Who imported the record

### Deleting an Entry

Administrators can delete individual entries by clicking the **trash icon** on any row.

> ⚠️ **Warning:** Deletion is permanent and cannot be undone.

---

## Frequently Asked Questions

**Q: What happens if I upload the same file twice?**
A: Duplicate rows (same NPK + date + start time) will be **updated** with the latest values.
No duplicate entries will be created.

**Q: What if an employee's NPK is not in the system?**
A: The entry is still imported — the name and NPK are saved as a snapshot. The employee link
will show as unresolved until the employee is added to the roster.

**Q: Why are some sheets skipped?**
A: Only sheets with numeric names (1–31) are processed. Reference sheets like `db_tanggal_kerja`
are automatically ignored.

**Q: How is the total hours calculated?**
A: The system recalculates hours from the raw start and end times, applying the standard break
deductions:

- For holiday shifts (HLR): 1.25 hours if a break window falls within the shift
- For workday shifts (HKN): 0.5 hours if a break window falls within the shift

---

## Troubleshooting

| Issue                               | Solution                                                                               |
| ----------------------------------- | -------------------------------------------------------------------------------------- |
| "Gagal menyimpan file sementara"    | Check that the server's `storage/app/private` directory is writable.                   |
| Upload succeeds but 0 rows imported | Ensure the Excel sheets are numbered (1–31) and data starts at row 13.                 |
| File rejected at upload             | Confirm the file is `.xlsx` or `.xls` and under 10 MB. PDFs and CSVs are not accepted. |
| Wrong month shown in entries        | Use the year and month filter at the top of the page to select the correct period.     |
