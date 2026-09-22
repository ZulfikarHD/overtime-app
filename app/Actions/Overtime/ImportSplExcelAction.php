<?php

namespace App\Actions\Overtime;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Section;
use App\Models\SplEntry;
use App\Models\User;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportSplExcelAction
{
    /**
     * Row index (1-based) where data starts in each SPL sheet.
     * The header row is row 11 (index 10), data begins at row 13 (index 12).
     */
    private const DATA_START_ROW = 13;

    /**
     * Row / column positions of the sheet header fields.
     * Row 6, col H (8) → Department name
     * Row 7, col H (8) → Section name
     */
    private const HEADER_ROW_DEPT = 6;

    private const HEADER_ROW_SECTION = 7;

    private const HEADER_COL_VALUE = 8;  // column H (1-based)

    /**
     * Column indices (1-based) in the SPL template.
     */
    private const COL_NO = 1;

    private const COL_NAMA = 2;

    private const COL_NPK = 3;

    private const COL_KODE_HARI = 4;

    private const COL_MULAI = 5;

    private const COL_SELESAI = 6;

    private const COL_TOTAL_JAM = 7;

    private const COL_JENIS_PEKERJAAN = 8;

    private const COL_TYPE_OT = 10;

    private const COL_KETERANGAN = 11;

    private const COL_DESCRIPTION = 12;

    private const COL_ACTION = 13;

    /**
     * Parse and upsert SPL entries from the uploaded Excel file.
     *
     * @param  string  $filePath  Absolute path to the uploaded .xlsx file
     * @param  int  $importYear  Year of the SPL document
     * @param  int  $importMonth  Month (1–12) of the SPL document
     * @param  User  $importedBy  Who triggered the import
     * @return array{imported: int, updated: int, skipped: int, errors: list<string>}
     */
    public function execute(string $filePath, int $importYear, int $importMonth, User $importedBy): array
    {
        $spreadsheet = IOFactory::load($filePath);

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        // Build a case-insensitive lookup map for sections and departments
        // so we can resolve names from the SPL header without N+1 queries.
        /** @var array<string, int> $sectionMap  lowercase(name) → id */
        $sectionMap = Section::pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id])
            ->all();

        /** @var array<string, int> $deptMap  lowercase(name) → id */
        $deptMap = Department::pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id])
            ->all();

        // Sheets are named '1' through '31' (day-of-month)
        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            $dayNumber = (int) $sheetName;

            if ($dayNumber < 1 || $dayNumber > 31) {
                continue; // skip non-date sheets (e.g. db_tanggal_kerja_spl, db_kode_hari_ot)
            }

            // Validate day exists in the month
            try {
                $realizationDate = Carbon::createFromDate($importYear, $importMonth, $dayNumber)->format('Y-m-d');
            } catch (\Exception) {
                $skipped++;

                continue;
            }

            $sheet = $spreadsheet->getSheetByName($sheetName);

            if (! $sheet) {
                continue;
            }

            // --- Read dept and section from this sheet's header ---
            $deptNameRaw = trim((string) ($this->cellValue($sheet, self::HEADER_COL_VALUE, self::HEADER_ROW_DEPT) ?? ''));
            $sectionNameRaw = trim((string) ($this->cellValue($sheet, self::HEADER_COL_VALUE, self::HEADER_ROW_SECTION) ?? ''));

            $sheetSectionId = $sectionMap[mb_strtolower($sectionNameRaw)] ?? null;
            $sheetDeptId = $deptMap[mb_strtolower($deptNameRaw)] ?? null;

            // If section resolved but dept not, try to derive dept from the matched section
            if ($sheetSectionId && ! $sheetDeptId) {
                $sheetDeptId = Section::find($sheetSectionId)?->department_id;
            }

            $maxRow = $sheet->getHighestDataRow();

            for ($row = self::DATA_START_ROW; $row <= $maxRow; $row++) {
                $npkRaw = $this->cellValue($sheet, self::COL_NPK, $row);
                $namaRaw = $this->cellValue($sheet, self::COL_NAMA, $row);

                // Skip empty / template filler rows
                if (empty($npkRaw) && empty($namaRaw)) {
                    continue;
                }

                $npk = (string) $npkRaw;
                $nama = trim((string) $namaRaw);

                if (empty($npk) || empty($nama)) {
                    continue;
                }

                // Parse times
                $mulaiRaw = $this->cellValue($sheet, self::COL_MULAI, $row);
                $selesaiRaw = $this->cellValue($sheet, self::COL_SELESAI, $row);

                try {
                    $startTime = $this->parseTime($mulaiRaw);
                    $endTime = $this->parseTime($selesaiRaw);
                } catch (\Throwable $e) {
                    $errors[] = "Sheet {$sheetName}, Row {$row} ({$nama}): Waktu tidak valid — {$e->getMessage()}";
                    $skipped++;

                    continue;
                }

                // Parse remaining fields
                $kodeHari = strtoupper(trim((string) $this->cellValue($sheet, self::COL_KODE_HARI, $row)));
                $totalJam = $this->parseTotalHours($mulaiRaw, $selesaiRaw, $kodeHari);
                $jenisPekerjaan = trim((string) $this->cellValue($sheet, self::COL_JENIS_PEKERJAAN, $row)) ?: null;
                $typeOt = $this->cellValue($sheet, self::COL_TYPE_OT, $row);
                $keterangan = trim((string) $this->cellValue($sheet, self::COL_KETERANGAN, $row)) ?: null;
                $description = trim((string) $this->cellValue($sheet, self::COL_DESCRIPTION, $row)) ?: null;
                $action = trim((string) $this->cellValue($sheet, self::COL_ACTION, $row)) ?: null;

                // Try to resolve employee from master data
                $employee = Employee::where('npk', $npk)->first();

                // Employee's own section/dept take priority; fall back to sheet header
                $resolvedSectionId = $employee?->section_id ?? $sheetSectionId;
                $resolvedDeptId = $employee?->department_id ?? $sheetDeptId;

                $data = [
                    'employee_id' => $employee?->id,
                    'employee_name_snapshot' => $nama,
                    'section_id' => $resolvedSectionId,
                    'department_id' => $resolvedDeptId,
                    'section_name_snapshot' => $sectionNameRaw ?: null,
                    'department_name_snapshot' => $deptNameRaw ?: null,
                    'day_type' => in_array($kodeHari, ['HLR', 'HKN']) ? $kodeHari : 'HKN',
                    'end_time' => $endTime,
                    'total_hours' => $totalJam,
                    'jenis_pekerjaan' => $jenisPekerjaan,
                    'type_ot_code' => $typeOt ? (int) $typeOt : null,
                    'keterangan_lembur' => $keterangan,
                    'description' => $description,
                    'action' => $action,
                    'imported_by_user_id' => $importedBy->id,
                ];

                // Upsert: match on npk_snapshot + realization_date + start_time
                // Use whereDate() so the comparison works on both MySQL DATE and SQLite text.
                $existing = SplEntry::where('npk_snapshot', $npk)
                    ->whereDate('realization_date', $realizationDate)
                    ->where('start_time', $startTime)
                    ->first();

                if ($existing) {
                    $existing->update($data);
                    $updated++;
                } else {
                    SplEntry::create(array_merge($data, [
                        'npk_snapshot' => $npk,
                        'realization_date' => $realizationDate,
                        'start_time' => $startTime,
                    ]));
                    $imported++;
                }
            }
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Parse Excel time value to HH:MM:SS string.
     * Handles datetime.time objects (Python-style strings), Excel serial numbers, and string times.
     *
     * @throws \InvalidArgumentException
     */
    private function parseTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            throw new \InvalidArgumentException('Nilai waktu kosong');
        }

        // Already a PHP DateTimeInterface from PhpSpreadsheet
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:s');
        }

        // Excel serial fraction (e.g. 0.3125 = 07:30)
        if (is_float($value) || (is_string($value) && is_numeric($value) && str_contains((string) $value, '.'))) {
            $serial = (float) $value;
            // Only the fractional part represents time
            $fraction = $serial - (int) $serial;
            $totalSeconds = (int) round($fraction * 86400);
            $h = intdiv($totalSeconds, 3600);
            $m = intdiv($totalSeconds % 3600, 60);
            $s = $totalSeconds % 60;

            return sprintf('%02d:%02d:%02d', $h, $m, $s);
        }

        // String like "07:30" or "07:30:00"
        if (is_string($value) && preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', trim($value))) {
            $parts = explode(':', trim($value));
            $h = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
            $m = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
            $s = isset($parts[2]) ? str_pad($parts[2], 2, '0', STR_PAD_LEFT) : '00';

            return "{$h}:{$m}:{$s}";
        }

        throw new \InvalidArgumentException("Format waktu tidak dikenal: {$value}");
    }

    /**
     * Calculate total hours from start to end, applying SPL deduction rules.
     *
     * SPL rules (from Excel formula logic):
     * - If day type is HLR (holiday):
     *   - If break time falls within OT window: floor to nearest 0.25h - 1.25h
     *   - Otherwise: floor to nearest 0.25h
     * - If day type is HKN (workday):
     *   - If break time falls within OT window: floor to nearest 0.25h - 0.5h
     *   - Otherwise: floor to nearest 0.25h
     */
    private function parseTotalHours(mixed $startRaw, mixed $endRaw, string $kodeHari): float
    {
        try {
            $startStr = $this->parseTime($startRaw);
            $endStr = $this->parseTime($endRaw);

            [$sh, $sm] = explode(':', $startStr);
            [$eh, $em] = explode(':', $endStr);

            $startMinutes = (int) $sh * 60 + (int) $sm;
            $endMinutes = (int) $eh * 60 + (int) $em;

            // Handle overnight
            if ($endMinutes <= $startMinutes) {
                $endMinutes += 24 * 60;
            }

            $durationHours = ($endMinutes - $startMinutes) / 60.0;

            // Break deduction: check if 12:46 or 18:46 falls within the window
            $break1 = 12 * 60 + 46;
            $break2 = 18 * 60 + 46;
            $hasBreak = ($startMinutes <= $break1 && $break1 <= $endMinutes)
                || ($startMinutes <= $break2 && $break2 <= $endMinutes);

            // Floor to nearest 0.25
            $floored = floor($durationHours / 0.25) * 0.25;

            $deduction = 0.0;
            if ($hasBreak) {
                $deduction = $kodeHari === 'HLR' ? 1.25 : 0.5;
            }

            return max(0.0, round($floored - $deduction, 2));
        } catch (\Throwable) {
            return 0.0;
        }
    }

    /**
     * Get a cell value by 1-based column and row indices.
     * PhpSpreadsheet 5.x removed getCellByColumnAndRow(); this is the portable replacement.
     */
    private function cellValue(Worksheet $sheet, int $col, int $row): mixed
    {
        return $sheet->getCell(Coordinate::stringFromColumnIndex($col).$row)->getValue();
    }
}
