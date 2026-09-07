<?php

namespace App\Services;

use App\Models\OperationalCalendar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationalCalendarService
{
    /**
     * Standard national holidays dictionary for Indonesian calendar.
     *
     * @var array<int, array<string, string>>
     */
    protected array $standardNationalHolidays = [
        2025 => [
            '2025-01-01' => 'Tahun Baru 2025 Masehi',
            '2025-01-27' => "Isra Mi'raj Nabi Muhammad SAW",
            '2025-01-29' => 'Tahun Baru Imlek 2576 Kongzili',
            '2025-03-29' => 'Hari Suci Nyepi (Tahun Baru Saka 1947)',
            '2025-03-31' => 'Hari Raya Idul Fitri 1446 H (Hari ke-1)',
            '2025-04-01' => 'Hari Raya Idul Fitri 1446 H (Hari ke-2)',
            '2025-04-18' => 'Wafat Yesus Kristus',
            '2025-05-01' => 'Hari Buruh Internasional',
            '2025-05-12' => 'Hari Raya Waisak 2569 BE',
            '2025-05-29' => 'Kenaikan Yesus Kristus',
            '2025-06-01' => 'Hari Lahir Pancasila',
            '2025-06-07' => 'Hari Raya Idul Adha 1446 H',
            '2025-06-27' => 'Tahun Baru Islam 1447 H',
            '2025-08-17' => 'Hari Kemerdekaan RI ke-80',
            '2025-09-05' => 'Maulid Nabi Muhammad SAW',
            '2025-12-25' => 'Hari Raya Natal',
        ],
        2026 => [
            '2026-01-01' => 'Tahun Baru 2026 Masehi',
            '2026-01-16' => "Isra Mi'raj Nabi Muhammad SAW",
            '2026-02-17' => 'Tahun Baru Imlek 2577 Kongzili',
            '2026-03-20' => 'Hari Suci Nyepi (Tahun Baru Saka 1948)',
            '2026-03-21' => 'Hari Raya Idul Fitri 1447 H (Hari ke-1)',
            '2026-03-22' => 'Hari Raya Idul Fitri 1447 H (Hari ke-2)',
            '2026-04-03' => 'Wafat Yesus Kristus',
            '2026-05-01' => 'Hari Buruh Internasional',
            '2026-05-14' => 'Kenaikan Yesus Kristus',
            '2026-05-27' => 'Hari Raya Idul Adha 1447 H',
            '2026-05-31' => 'Hari Raya Waisak 2570 BE',
            '2026-06-01' => 'Hari Lahir Pancasila',
            '2026-06-16' => 'Tahun Baru Islam 1448 H',
            '2026-08-17' => 'Hari Kemerdekaan RI ke-81',
            '2026-08-25' => 'Maulid Nabi Muhammad SAW',
            '2026-12-25' => 'Hari Raya Natal',
        ],
        2027 => [
            '2027-01-01' => 'Tahun Baru 2027 Masehi',
            '2027-02-06' => 'Tahun Baru Imlek 2578 Kongzili',
            '2027-03-10' => 'Hari Raya Idul Fitri 1448 H (Hari ke-1)',
            '2027-03-11' => 'Hari Raya Idul Fitri 1448 H (Hari ke-2)',
            '2027-03-26' => 'Wafat Yesus Kristus',
            '2027-05-01' => 'Hari Buruh Internasional',
            '2027-05-06' => 'Kenaikan Yesus Kristus',
            '2027-05-17' => 'Hari Raya Idul Adha 1448 H',
            '2027-05-20' => 'Hari Raya Waisak 2571 BE',
            '2027-06-01' => 'Hari Lahir Pancasila',
            '2027-06-06' => 'Tahun Baru Islam 1449 H',
            '2027-08-17' => 'Hari Kemerdekaan RI ke-82',
            '2027-08-14' => 'Maulid Nabi Muhammad SAW',
            '2027-12-25' => 'Hari Raya Natal',
        ],
    ];

    /**
     * Generate operational calendar rows for the given fiscal year.
     * Saturday/Sunday = HLR, Weekdays = HKN, with default national holidays.
     *
     * @param  array<string, string>  $nationalHolidays
     */
    public function generateForYear(int $year, array $nationalHolidays = []): int
    {
        $startDate = Carbon::createFromDate($year, 1, 1)->startOfDay();
        $endDate = Carbon::createFromDate($year, 12, 31)->endOfDay();

        $holidays = ! empty($nationalHolidays)
            ? $nationalHolidays
            : ($this->standardNationalHolidays[$year] ?? [
                "{$year}-01-01" => "Tahun Baru {$year} Masehi",
                "{$year}-05-01" => 'Hari Buruh Internasional',
                "{$year}-06-01" => 'Hari Lahir Pancasila',
                "{$year}-08-17" => 'Hari Kemerdekaan RI',
                "{$year}-12-25" => 'Hari Raya Natal',
            ]);

        $records = [];
        $current = $startDate->copy();
        $now = Carbon::now();

        while ($current->lte($endDate)) {
            $dateStr = $current->format('Y-m-d');
            $isWeekend = $current->isWeekend();
            $isPublicHoliday = isset($holidays[$dateStr]);

            $isHoliday = $isWeekend || $isPublicHoliday;
            $dayType = $isHoliday ? 'HLR' : 'HKN';
            $holidayName = $holidays[$dateStr] ?? ($isWeekend ? ($current->isSaturday() ? 'Sabtu Libur' : 'Minggu Libur') : null);
            $description = $isPublicHoliday
                ? "Hari Libur Nasional: {$holidayName}"
                : ($isWeekend ? 'Akhir Pekan' : 'Hari Kerja Normal');

            $records[] = [
                'calendar_date' => $dateStr,
                'day_type' => $dayType,
                'is_holiday' => $isHoliday,
                'holiday_name' => $holidayName,
                'description' => $description,
                'created_at' => $now,
            ];

            $current->addDay();
        }

        foreach (array_chunk($records, 100) as $chunk) {
            OperationalCalendar::upsert(
                $chunk,
                ['calendar_date'],
                ['day_type', 'is_holiday', 'holiday_name', 'description'],
            );
        }

        return count($records);
    }

    /**
     * Ensure the given year is seeded in the database.
     */
    public function ensureYearSeeded(int $year): void
    {
        $count = OperationalCalendar::whereYear('calendar_date', $year)->count();
        $expectedDays = Carbon::createFromDate($year, 1, 1)->isLeapYear() ? 366 : 365;

        if ($count < $expectedDays) {
            $this->generateForYear($year);
        }
    }

    /**
     * Get operational calendar records for a specific year and month.
     *
     * @return Collection<int, OperationalCalendar>
     */
    public function getMonthCalendar(int $year, int $month): Collection
    {
        $this->ensureYearSeeded($year);

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        return OperationalCalendar::query()
            ->whereBetween('calendar_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->orderBy('calendar_date')
            ->get();
    }

    /**
     * Get summary metrics for the given year and month.
     *
     * @return array{
     *     total_days: int,
     *     hkn_count: int,
     *     hlr_count: int,
     *     holiday_count: int
     * }
     */
    public function getMonthStats(int $year, int $month): array
    {
        $days = $this->getMonthCalendar($year, $month);

        $hknCount = $days->where('day_type', 'HKN')->count();
        $hlrCount = $days->where('day_type', 'HLR')->count();
        $holidayCount = $days->filter(function ($day) {
            return $day->is_holiday && $day->holiday_name !== 'Sabtu Libur' && $day->holiday_name !== 'Minggu Libur';
        })->count();

        return [
            'total_days' => $days->count(),
            'hkn_count' => $hknCount,
            'hlr_count' => $hlrCount,
            'holiday_count' => $holidayCount,
        ];
    }

    /**
     * Retrieve a calendar record for a specific date string (Y-m-d).
     */
    public function getByDate(string $date): OperationalCalendar
    {
        $carbonDate = Carbon::parse($date);
        $this->ensureYearSeeded($carbonDate->year);

        $record = OperationalCalendar::find($carbonDate->format('Y-m-d'))
            ?? OperationalCalendar::whereDate('calendar_date', $carbonDate->format('Y-m-d'))->first();

        if (! $record) {
            $isWeekend = $carbonDate->isWeekend();
            $record = OperationalCalendar::create([
                'calendar_date' => $carbonDate->format('Y-m-d'),
                'day_type' => $isWeekend ? 'HLR' : 'HKN',
                'is_holiday' => $isWeekend,
                'holiday_name' => $isWeekend ? ($carbonDate->isSaturday() ? 'Sabtu Libur' : 'Minggu Libur') : null,
                'description' => $isWeekend ? 'Akhir Pekan' : 'Hari Kerja Normal',
                'created_at' => now(),
            ]);
        }

        return $record;
    }

    /**
     * Update an operational calendar date.
     * Note: Changes take effect immediately for new submissions and do NOT affect past records.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateDate(string $date, array $data): OperationalCalendar
    {
        $calendar = $this->getByDate($date);

        $dayType = strtoupper(trim((string) ($data['day_type'] ?? $calendar->day_type)));
        if (! in_array($dayType, ['HKN', 'HLR'], true)) {
            $dayType = 'HKN';
        }

        $isHoliday = isset($data['is_holiday'])
            ? (bool) $data['is_holiday']
            : ($dayType === 'HLR');

        $holidayName = isset($data['holiday_name']) && trim((string) $data['holiday_name']) !== ''
            ? trim((string) $data['holiday_name'])
            : null;

        $description = isset($data['description']) && trim((string) $data['description']) !== ''
            ? trim((string) $data['description'])
            : null;

        if ($dayType === 'HKN') {
            $isHoliday = false;
            $holidayName = null;
            if ($description === null) {
                $description = 'Hari Kerja Normal';
            }
        } elseif ($dayType === 'HLR') {
            $isHoliday = true;
            if ($holidayName === null) {
                $carbon = Carbon::parse($date);
                $holidayName = $carbon->isSaturday() ? 'Sabtu Libur' : ($carbon->isSunday() ? 'Minggu Libur' : 'Hari Libur / Istirahat');
            }
            if ($description === null) {
                $description = 'Hari Libur / Istirahat';
            }
        }

        $calendar->update([
            'day_type' => $dayType,
            'is_holiday' => $isHoliday,
            'holiday_name' => $holidayName,
            'description' => $description,
        ]);

        return $calendar->refresh();
    }

    /**
     * Parse and audit a CSV file containing national holidays in-memory for pre-commit preview.
     * Columns: `date`, `holiday_name`, optional `description`.
     *
     * @return array{
     *     total: int,
     *     valid_count: int,
     *     error_count: int,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function parseAndValidateCsv(UploadedFile|string $file): array
    {
        $cleanupTemp = false;
        if ($file instanceof UploadedFile) {
            $filePath = $file->getRealPath();
        } elseif (is_string($file) && file_exists($file)) {
            $filePath = $file;
        } elseif (is_string($file)) {
            $temp = tempnam(sys_get_temp_dir(), 'csv_holiday_parse_');
            file_put_contents($temp, $file);
            $filePath = $temp;
            $cleanupTemp = true;
        } else {
            throw ValidationException::withMessages([
                'csv_file' => [__('The uploaded CSV file could not be read.')],
            ]);
        }

        if (! file_exists($filePath) || ! is_readable($filePath)) {
            throw ValidationException::withMessages([
                'csv_file' => [__('The uploaded CSV file could not be read.')],
            ]);
        }

        $handle = fopen($filePath, 'r');
        if (! $handle) {
            if ($cleanupTemp && file_exists($filePath)) {
                unlink($filePath);
            }
            throw ValidationException::withMessages([
                'csv_file' => [__('Failed to open CSV file.')],
            ]);
        }

        // Read header
        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            if ($cleanupTemp && file_exists($filePath)) {
                unlink($filePath);
            }
            throw ValidationException::withMessages([
                'csv_file' => [__('CSV file is empty.')],
            ]);
        }

        // Clean UTF-8 BOM if present
        $header[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header[0]);
        $normalizedHeader = array_map(fn ($col) => strtolower(trim((string) $col)), $header);

        $dateCol = null;
        if (in_array('date', $normalizedHeader, true)) {
            $dateCol = 'date';
        } elseif (in_array('calendar_date', $normalizedHeader, true)) {
            $dateCol = 'calendar_date';
        } elseif (in_array('tanggal', $normalizedHeader, true)) {
            $dateCol = 'tanggal';
        }

        if (! $dateCol || ! in_array('holiday_name', $normalizedHeader, true) && ! in_array('nama_hari_libur', $normalizedHeader, true)) {
            fclose($handle);
            if ($cleanupTemp && file_exists($filePath)) {
                unlink($filePath);
            }
            throw ValidationException::withMessages([
                'csv_file' => [__('Missing required CSV column: date, holiday_name')],
            ]);
        }

        $holidayNameCol = in_array('holiday_name', $normalizedHeader, true) ? 'holiday_name' : 'nama_hari_libur';
        $descCol = in_array('description', $normalizedHeader, true) ? 'description' : (in_array('keterangan', $normalizedHeader, true) ? 'keterangan' : null);

        $colIndices = array_flip($normalizedHeader);

        $seenDatesInCsv = [];
        $parsedRows = [];
        $rowNumber = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (count($data) === 1 && ($data[0] === null || trim((string) $data[0]) === '')) {
                continue;
            }

            $errors = [];

            $dateRaw = isset($colIndices[$dateCol]) && isset($data[$colIndices[$dateCol]])
                ? trim((string) $data[$colIndices[$dateCol]])
                : '';
            $holidayNameRaw = isset($colIndices[$holidayNameCol]) && isset($data[$colIndices[$holidayNameCol]])
                ? trim((string) $data[$colIndices[$holidayNameCol]])
                : '';
            $descRaw = $descCol && isset($colIndices[$descCol]) && isset($data[$colIndices[$descCol]])
                ? trim((string) $data[$colIndices[$descCol]])
                : '';

            $normalizedDate = null;
            if ($dateRaw === '') {
                $errors[] = __('Baris :line: Tanggal wajib diisi.', ['line' => $rowNumber]);
            } else {
                try {
                    $parsedCarbon = Carbon::parse($dateRaw);
                    $normalizedDate = $parsedCarbon->format('Y-m-d');
                } catch (\Throwable) {
                    $errors[] = __('Baris :line: Format tanggal tidak valid (\':date\'). Gunakan format YYYY-MM-DD.', [
                        'line' => $rowNumber,
                        'date' => $dateRaw,
                    ]);
                }
            }

            if ($normalizedDate !== null) {
                if (isset($seenDatesInCsv[$normalizedDate])) {
                    $errors[] = __('Baris :line: Tanggal \':date\' duplikat di dalam file CSV ini (muncul di baris :prev_line).', [
                        'line' => $rowNumber,
                        'date' => $normalizedDate,
                        'prev_line' => $seenDatesInCsv[$normalizedDate],
                    ]);
                } else {
                    $seenDatesInCsv[$normalizedDate] = $rowNumber;
                }
            }

            if ($holidayNameRaw === '') {
                $errors[] = __('Baris :line: Nama hari libur wajib diisi.', ['line' => $rowNumber]);
            } elseif (strlen($holidayNameRaw) > 100) {
                $errors[] = __('Baris :line: Nama hari libur maksimal 100 karakter.', ['line' => $rowNumber]);
            }

            if ($descRaw !== '' && strlen($descRaw) > 255) {
                $errors[] = __('Baris :line: Keterangan maksimal 255 karakter.', ['line' => $rowNumber]);
            }

            $isValid = count($errors) === 0;

            $parsedRows[] = [
                'row_number' => $rowNumber,
                'date' => $normalizedDate ?? $dateRaw,
                'holiday_name' => $holidayNameRaw,
                'description' => $descRaw !== '' ? $descRaw : "Hari Libur Nasional: {$holidayNameRaw}",
                'day_type' => 'HLR',
                'is_holiday' => true,
                'is_valid' => $isValid,
                'errors' => $errors,
            ];
        }

        fclose($handle);

        if ($cleanupTemp && file_exists($filePath)) {
            unlink($filePath);
        }

        $validCount = count(array_filter($parsedRows, fn ($r) => $r['is_valid']));
        $errorCount = count($parsedRows) - $validCount;

        return [
            'total' => count($parsedRows),
            'valid_count' => $validCount,
            'error_count' => $errorCount,
            'rows' => $parsedRows,
        ];
    }

    /**
     * Commit pre-validated holiday rows to the operational calendar.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{imported: int, errors: list<string>}
     */
    public function importRows(array $rows): array
    {
        $validRows = array_filter($rows, function ($r) {
            $isValidFlag = isset($r['is_valid']) ? (bool) $r['is_valid'] : (! isset($r['errors']) || empty($r['errors']));

            return $isValidFlag && ! empty($r['date']) && ! empty($r['holiday_name']);
        });

        if (empty($validRows)) {
            return ['imported' => 0, 'errors' => [__('No valid rows found to import.')]];
        }

        $now = now();
        $insertData = [];

        foreach ($validRows as $row) {
            $date = Carbon::parse((string) $row['date'])->format('Y-m-d');
            $holidayName = trim((string) $row['holiday_name']);
            $desc = isset($row['description']) && trim((string) $row['description']) !== ''
                ? trim((string) $row['description'])
                : "Hari Libur Nasional: {$holidayName}";

            $insertData[] = [
                'calendar_date' => $date,
                'day_type' => 'HLR',
                'is_holiday' => true,
                'holiday_name' => $holidayName,
                'description' => $desc,
                'created_at' => $now,
            ];
        }

        DB::transaction(function () use ($insertData) {
            foreach (array_chunk($insertData, 100) as $chunk) {
                OperationalCalendar::upsert(
                    $chunk,
                    ['calendar_date'],
                    ['day_type', 'is_holiday', 'holiday_name', 'description'],
                );
            }
        });

        return [
            'imported' => count($insertData),
            'errors' => [],
        ];
    }

    /**
     * Generate standard CSV template content for national holidays import.
     */
    public function generateCsvTemplate(): string
    {
        $headers = ['date', 'holiday_name', 'description'];

        $sampleRows = [
            ['2026-08-17', 'Hari Kemerdekaan RI ke-81', 'Hari Libur Nasional'],
            ['2026-12-25', 'Hari Raya Natal', 'Hari Libur Nasional'],
            ['2027-01-01', 'Tahun Baru 2027 Masehi', 'Hari Libur Nasional'],
        ];

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);

        foreach ($sampleRows as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return (string) $csvContent;
    }
}
