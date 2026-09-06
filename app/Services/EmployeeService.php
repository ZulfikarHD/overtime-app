<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Section;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeService
{
    /**
     * Create a new employee record.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(array $data): Employee
    {
        $npk = strtoupper(trim((string) $data['npk']));
        $departmentId = (int) $data['department_id'];
        $sectionId = (int) $data['section_id'];

        $this->validateSectionBelongsToDepartment($sectionId, $departmentId);

        $hourlyRate = null;
        if (isset($data['hourly_rate']) && $data['hourly_rate'] !== '' && $data['hourly_rate'] !== null) {
            $hourlyRate = (float) $data['hourly_rate'];
        }

        return Employee::create([
            'npk' => $npk,
            'department_id' => $departmentId,
            'section_id' => $sectionId,
            'full_name' => trim((string) $data['full_name']),
            'job_position' => trim((string) $data['job_position']),
            'hourly_rate' => $hourlyRate,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
    }

    /**
     * Update an existing employee record.
     * Note: 'npk' is strictly immutable per Business Rule BR-03.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(Employee $employee, array $data): Employee
    {
        $departmentId = isset($data['department_id']) ? (int) $data['department_id'] : $employee->department_id;
        $sectionId = isset($data['section_id']) ? (int) $data['section_id'] : $employee->section_id;

        $this->validateSectionBelongsToDepartment($sectionId, $departmentId);

        $hourlyRate = $employee->hourly_rate;
        if (array_key_exists('hourly_rate', $data)) {
            $hourlyRate = ($data['hourly_rate'] !== '' && $data['hourly_rate'] !== null)
                ? (float) $data['hourly_rate']
                : null;
        }

        $isActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $employee->is_active;

        $employee->update([
            'department_id' => $departmentId,
            'section_id' => $sectionId,
            'full_name' => isset($data['full_name']) ? trim((string) $data['full_name']) : $employee->full_name,
            'job_position' => isset($data['job_position']) ? trim((string) $data['job_position']) : $employee->job_position,
            'hourly_rate' => $hourlyRate,
            'is_active' => $isActive,
        ]);

        return $employee;
    }

    /**
     * Validate that section belongs to the department.
     *
     * @throws ValidationException
     */
    protected function validateSectionBelongsToDepartment(int $sectionId, int $departmentId): void
    {
        $section = Section::find($sectionId);

        if (! $section || (int) $section->department_id !== $departmentId) {
            throw ValidationException::withMessages([
                'section_id' => [__('The selected section does not belong to the selected department.')],
            ]);
        }
    }

    /**
     * Determine whether the employee can be safely deleted.
     *
     * @return array{allowed: bool, reason: string|null}
     */
    public function canDelete(Employee $employee): array
    {
        $overtimeCount = $employee->overtimeItems()->count();

        if ($overtimeCount > 0) {
            return [
                'allowed' => false,
                'reason' => __('Cannot delete employee because they have :count overtime record(s). Deactivate instead to preserve historical integrity.', ['count' => $overtimeCount]),
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
        ];
    }

    /**
     * Delete employee if integrity checks allow.
     *
     * @throws ValidationException
     */
    public function delete(Employee $employee): void
    {
        $check = $this->canDelete($employee);

        if (! $check['allowed']) {
            throw ValidationException::withMessages([
                'delete' => [$check['reason'] ?? __('Employee cannot be deleted.')],
            ]);
        }

        $employee->delete();
    }

    /**
     * Parse and audit a CSV file in-memory for pre-commit preview.
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
            $temp = tempnam(sys_get_temp_dir(), 'csv_parse_');
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

        $expectedColumns = ['npk', 'full_name', 'department_code', 'section_code', 'job_position'];
        foreach ($expectedColumns as $col) {
            if (! in_array($col, $normalizedHeader, true)) {
                fclose($handle);
                if ($cleanupTemp && file_exists($filePath)) {
                    unlink($filePath);
                }
                throw ValidationException::withMessages([
                    'csv_file' => [__('Missing required CSV column: :col', ['col' => $col])],
                ]);
            }
        }

        $colIndices = array_flip($normalizedHeader);

        // Preload existing departments, sections, and NPKs for O(1) in-memory lookup
        $departments = Department::all()->keyBy(fn ($d) => strtoupper($d->code));
        $sections = Section::with('department')->get();
        $existingNpks = Employee::pluck('npk')->map(fn ($n) => strtoupper($n))->flip()->toArray();

        // Map section code to section model
        $sectionsByCode = [];
        foreach ($sections as $s) {
            $sectionsByCode[strtoupper($s->code)] = $s;
        }

        $seenNpksInCsv = [];
        $parsedRows = [];
        $rowNumber = 1; // 1 is header

        while (($data = fgetcsv($handle)) !== false) {
            $rowNumber++;

            // Skip entirely empty lines
            if (count($data) === 1 && ($data[0] === null || trim((string) $data[0]) === '')) {
                continue;
            }

            $errors = [];

            $npkRaw = isset($colIndices['npk']) && isset($data[$colIndices['npk']]) ? trim((string) $data[$colIndices['npk']]) : '';
            $fullName = isset($colIndices['full_name']) && isset($data[$colIndices['full_name']]) ? trim((string) $data[$colIndices['full_name']]) : '';
            $deptCodeRaw = isset($colIndices['department_code']) && isset($data[$colIndices['department_code']]) ? trim((string) $data[$colIndices['department_code']]) : '';
            $sectionCodeRaw = isset($colIndices['section_code']) && isset($data[$colIndices['section_code']]) ? trim((string) $data[$colIndices['section_code']]) : '';
            $jobPosition = isset($colIndices['job_position']) && isset($data[$colIndices['job_position']]) ? trim((string) $data[$colIndices['job_position']]) : '';
            $hourlyRateRaw = isset($colIndices['hourly_rate']) && isset($data[$colIndices['hourly_rate']]) ? trim((string) $data[$colIndices['hourly_rate']]) : '';

            $npkUpper = strtoupper($npkRaw);
            $deptCodeUpper = strtoupper($deptCodeRaw);
            $sectionCodeUpper = strtoupper($sectionCodeRaw);

            // NPK Validation
            if ($npkRaw === '') {
                $errors[] = __('Baris :line: NPK wajib diisi.', ['line' => $rowNumber]);
            } elseif (strlen($npkRaw) > 20) {
                $errors[] = __('Baris :line: NPK maksimal 20 karakter.', ['line' => $rowNumber]);
            } elseif (isset($existingNpks[$npkUpper])) {
                $errors[] = __('Baris :line: NPK \':npk\' sudah terdaftar pada sistem.', ['line' => $rowNumber, 'npk' => $npkRaw]);
            } elseif (isset($seenNpksInCsv[$npkUpper])) {
                $errors[] = __('Baris :line: NPK \':npk\' duplikat di dalam file CSV ini (muncul di baris :prev_line).', [
                    'line' => $rowNumber,
                    'npk' => $npkRaw,
                    'prev_line' => $seenNpksInCsv[$npkUpper],
                ]);
            } else {
                $seenNpksInCsv[$npkUpper] = $rowNumber;
            }

            // Full Name Validation
            if ($fullName === '') {
                $errors[] = __('Baris :line: Nama lengkap wajib diisi.', ['line' => $rowNumber]);
            } elseif (strlen($fullName) > 150) {
                $errors[] = __('Baris :line: Nama lengkap maksimal 150 karakter.', ['line' => $rowNumber]);
            }

            // Department Validation
            $department = $departments->get($deptCodeUpper);
            if ($deptCodeRaw === '') {
                $errors[] = __('Baris :line: Kode departemen wajib diisi.', ['line' => $rowNumber]);
            } elseif (! $department) {
                $errors[] = __('Baris :line: Kode Departemen \':dept\' tidak ditemukan.', ['line' => $rowNumber, 'dept' => $deptCodeRaw]);
            }

            // Section Validation
            $section = $sectionsByCode[$sectionCodeUpper] ?? null;
            if ($sectionCodeRaw === '') {
                $errors[] = __('Baris :line: Kode seksi wajib diisi.', ['line' => $rowNumber]);
            } elseif (! $section) {
                $errors[] = __('Baris :line: Kode Seksi \':section\' tidak ditemukan.', ['line' => $rowNumber, 'section' => $sectionCodeRaw]);
            } elseif ($department && (int) $section->department_id !== (int) $department->id) {
                $errors[] = __('Baris :line: Seksi \':section\' bukan merupakan bagian dari Departemen \':dept\'.', [
                    'line' => $rowNumber,
                    'section' => $sectionCodeRaw,
                    'dept' => $deptCodeRaw,
                ]);
            }

            // Job Position Validation
            if ($jobPosition === '') {
                $errors[] = __('Baris :line: Jabatan / posisi wajib diisi.', ['line' => $rowNumber]);
            } elseif (strlen($jobPosition) > 100) {
                $errors[] = __('Baris :line: Jabatan maksimal 100 karakter.', ['line' => $rowNumber]);
            }

            // Hourly Rate Validation
            $hourlyRate = null;
            if ($hourlyRateRaw !== '') {
                $cleanRate = str_replace(['Rp', 'rp', '.', ' '], '', $hourlyRateRaw);
                $cleanRate = str_replace(',', '.', $cleanRate);

                if (! is_numeric($cleanRate) || (float) $cleanRate < 0) {
                    $errors[] = __('Baris :line: Tarif per jam harus berupa angka non-negatif.', ['line' => $rowNumber]);
                } else {
                    $hourlyRate = (float) $cleanRate;
                }
            }

            $isValid = count($errors) === 0;

            $parsedRows[] = [
                'row_number' => $rowNumber,
                'npk' => $npkUpper,
                'full_name' => $fullName,
                'department_code' => $deptCodeUpper,
                'department_name' => $department?->name ?? $deptCodeRaw,
                'department_id' => $department?->id,
                'section_code' => $sectionCodeUpper,
                'section_name' => $section?->name ?? $sectionCodeRaw,
                'section_id' => $section?->id,
                'job_position' => $jobPosition,
                'hourly_rate' => $hourlyRate,
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
     * Import validated rows into the database.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{imported: int, errors: list<string>}
     */
    public function importRows(array $rows): array
    {
        $validRows = array_filter($rows, function ($r) {
            $isValidFlag = ! array_key_exists('is_valid', $r) || (bool) $r['is_valid'];

            return $isValidFlag && ! empty($r['npk']) && ! empty($r['department_id']) && ! empty($r['section_id']);
        });

        if (empty($validRows)) {
            return ['imported' => 0, 'errors' => [__('No valid rows found to import.')]];
        }

        $now = now();
        $insertData = [];

        foreach ($validRows as $row) {
            $insertData[] = [
                'npk' => strtoupper(trim((string) $row['npk'])),
                'department_id' => (int) $row['department_id'],
                'section_id' => (int) $row['section_id'],
                'full_name' => trim((string) $row['full_name']),
                'job_position' => trim((string) $row['job_position']),
                'hourly_rate' => isset($row['hourly_rate']) && $row['hourly_rate'] !== null ? (float) $row['hourly_rate'] : null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($insertData) {
            foreach (array_chunk($insertData, 100) as $chunk) {
                Employee::insert($chunk);
            }
        });

        return [
            'imported' => count($insertData),
            'errors' => [],
        ];
    }

    /**
     * Generate standard CSV template content for employee import.
     */
    public function generateCsvTemplate(): string
    {
        $headers = ['npk', 'full_name', 'department_code', 'section_code', 'job_position', 'hourly_rate'];

        $sampleDepartments = Department::with('sections')->take(2)->get();
        $sampleRows = [];

        if ($sampleDepartments->isNotEmpty()) {
            $counter = 1;
            foreach ($sampleDepartments as $dept) {
                $section = $dept->sections->first();
                $secCode = $section ? $section->code : 'SEC1';
                $sampleRows[] = [
                    'EMP-'.sprintf('%05d', $counter++),
                    'Nama Contoh '.$counter,
                    $dept->code,
                    $secCode,
                    'Line Operator',
                    $dept->default_hourly_rate ?? '35000',
                ];
            }
        } else {
            $sampleRows = [
                ['EMP-10001', 'Budi Santoso', 'PROD', 'STP', 'Line Operator', '35000'],
                ['EMP-10002', 'Siti Rahayu', 'PROD', 'ASSY', 'Team Leader', ''],
            ];
        }

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);
        foreach ($sampleRows as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return (string) $content;
    }
}
