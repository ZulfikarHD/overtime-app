<?php

namespace App\Services;

use App\Models\Department;
use App\Models\OvertimeBudget;
use App\Models\Section;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OvertimeBudgetService
{
    /**
     * Get the consolidated planning matrix and summary statistics.
     *
     * @return array{
     *     departments: list<array{id: int, code: string, name: string}>,
     *     selected_department: array{id: int, code: string, name: string, default_hourly_rate: float}|null,
     *     fiscal_year: int,
     *     fiscal_month: int,
     *     sections: list<array<string, mixed>>,
     *     summary: array{
     *         total_planned_hours: float,
     *         total_estimated_cost_idr: float,
     *         configured_sections_count: int,
     *         total_sections_count: int,
     *         coverage_percentage: float
     *     }
     * }
     */
    public function getPlanningMatrix(int $year, int $month, ?int $departmentId, User $user): array
    {
        // 1. Resolve available departments based on user role
        if ($user->isManager()) {
            $departmentsQuery = Department::where('id', $user->department_id)->where('is_active', true);
            $targetDeptId = (int) $user->department_id;
        } else {
            $departmentsQuery = Department::where('is_active', true)->orderBy('code');
            $targetDeptId = $departmentId ?? Department::where('is_active', true)->orderBy('code')->value('id');
        }

        /** @var list<array{id: int, code: string, name: string}> $departments */
        $departments = $departmentsQuery->get(['id', 'code', 'name'])->toArray();

        if (! $targetDeptId) {
            return [
                'departments' => $departments,
                'selected_department' => null,
                'fiscal_year' => $year,
                'fiscal_month' => $month,
                'sections' => [],
                'summary' => [
                    'total_planned_hours' => 0.0,
                    'total_estimated_cost_idr' => 0.0,
                    'configured_sections_count' => 0,
                    'total_sections_count' => 0,
                    'coverage_percentage' => 0.0,
                ],
            ];
        }

        $department = Department::with(['sections' => fn ($q) => $q->where('is_active', true)->orderBy('code')])
            ->find($targetDeptId);

        if (! $department) {
            return [
                'departments' => $departments,
                'selected_department' => null,
                'fiscal_year' => $year,
                'fiscal_month' => $month,
                'sections' => [],
                'summary' => [
                    'total_planned_hours' => 0.0,
                    'total_estimated_cost_idr' => 0.0,
                    'configured_sections_count' => 0,
                    'total_sections_count' => 0,
                    'coverage_percentage' => 0.0,
                ],
            ];
        }

        // 2. Fetch existing overtime budgets for this department and fiscal period
        $budgets = OvertimeBudget::forPeriod($year, $month)
            ->forDepartment($department->id)
            ->get()
            ->keyBy('section_id');

        $sectionsData = [];
        $totalPlannedHours = 0.0;
        $totalEstimatedCost = 0.0;
        $configuredCount = 0;

        $hourlyRate = (float) $department->default_hourly_rate;

        foreach ($department->sections as $section) {
            /** @var OvertimeBudget|null $budget */
            $budget = $budgets->get($section->id);
            $hasBudget = $budget !== null;

            if ($hasBudget) {
                $configuredCount++;
                $plannedHours = (float) $budget->planned_hours;
                $plannedCost = (float) $budget->planned_cost_idr;
                $w1 = (float) $budget->week1_planned_hours;
                $w2 = (float) $budget->week2_planned_hours;
                $w3 = (float) $budget->week3_planned_hours;
                $w4 = (float) $budget->week4_planned_hours;
                $w5 = (float) $budget->week5_planned_hours;
                $weeklySum = round($w1 + $w2 + $w3 + $w4 + $w5, 2);
                $hasMismatch = abs($weeklySum - $plannedHours) > 0.05;
                $budgetId = $budget->id;
                $updatedAt = $budget->updated_at?->format('d/m/Y H:i');
            } else {
                $plannedHours = 0.0;
                $plannedCost = 0.0;
                $w1 = 0.0;
                $w2 = 0.0;
                $w3 = 0.0;
                $w4 = 0.0;
                $w5 = 0.0;
                $weeklySum = 0.0;
                $hasMismatch = false;
                $budgetId = null;
                $updatedAt = null;
            }

            $totalPlannedHours += $plannedHours;
            $totalEstimatedCost += $plannedCost;

            $sectionsData[] = [
                'section_id' => $section->id,
                'section_code' => $section->code,
                'section_name' => $section->name,
                'has_budget' => $hasBudget,
                'budget_id' => $budgetId,
                'planned_hours' => $plannedHours,
                'planned_cost_idr' => $plannedCost,
                'week1_planned_hours' => $w1,
                'week2_planned_hours' => $w2,
                'week3_planned_hours' => $w3,
                'week4_planned_hours' => $w4,
                'week5_planned_hours' => $w5,
                'weekly_sum' => $weeklySum,
                'has_mismatch' => $hasMismatch,
                'updated_at' => $updatedAt,
            ];
        }

        $totalSections = count($department->sections);
        $coveragePct = $totalSections > 0 ? round(($configuredCount / $totalSections) * 100, 1) : 0.0;

        return [
            'departments' => $departments,
            'selected_department' => [
                'id' => $department->id,
                'code' => $department->code,
                'name' => $department->name,
                'default_hourly_rate' => $hourlyRate,
            ],
            'fiscal_year' => $year,
            'fiscal_month' => $month,
            'sections' => $sectionsData,
            'summary' => [
                'total_planned_hours' => round($totalPlannedHours, 2),
                'total_estimated_cost_idr' => round($totalEstimatedCost, 2),
                'configured_sections_count' => $configuredCount,
                'total_sections_count' => $totalSections,
                'coverage_percentage' => $coveragePct,
            ],
        ];
    }

    /**
     * Upsert an overtime budget record for a section and fiscal period.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function upsertBudget(array $data, User $user): OvertimeBudget
    {
        $deptId = (int) $data['department_id'];
        $sectionId = isset($data['section_id']) && $data['section_id'] !== '' ? (int) $data['section_id'] : null;
        $year = (int) $data['fiscal_year'];
        $month = (int) $data['fiscal_month'];
        $plannedHours = round((float) $data['planned_hours'], 2);

        // Scoping check for manager
        if ($user->isManager() && $deptId !== (int) $user->department_id) {
            throw ValidationException::withMessages([
                'department_id' => [__('You are only authorized to set budgets for your assigned department.')],
            ]);
        }

        $department = Department::findOrFail($deptId);

        if ($sectionId !== null) {
            $section = Section::findOrFail($sectionId);
            if ((int) $section->department_id !== $deptId) {
                throw ValidationException::withMessages([
                    'section_id' => [__('The selected section does not belong to the selected department.')],
                ]);
            }
        }

        // Automatic 5-week distribution if not explicitly specified
        $hasCustomWeeks = isset($data['week1_planned_hours'])
            || isset($data['week2_planned_hours'])
            || isset($data['week3_planned_hours'])
            || isset($data['week4_planned_hours'])
            || isset($data['week5_planned_hours']);

        if ($hasCustomWeeks) {
            $w1 = round((float) ($data['week1_planned_hours'] ?? 0), 2);
            $w2 = round((float) ($data['week2_planned_hours'] ?? 0), 2);
            $w3 = round((float) ($data['week3_planned_hours'] ?? 0), 2);
            $w4 = round((float) ($data['week4_planned_hours'] ?? 0), 2);
            $w5 = round((float) ($data['week5_planned_hours'] ?? 0), 2);
        } else {
            $weeklyAvg = round($plannedHours / 4.3, 2);
            $w1 = $weeklyAvg;
            $w2 = $weeklyAvg;
            $w3 = $weeklyAvg;
            $w4 = $weeklyAvg;
            $w5 = round(max(0, $plannedHours - ($weeklyAvg * 4)), 2);
        }

        $hourlyRate = (float) $department->default_hourly_rate;
        $plannedCost = round($plannedHours * $hourlyRate, 2);

        return OvertimeBudget::updateOrCreate(
            [
                'department_id' => $deptId,
                'section_id' => $sectionId,
                'fiscal_year' => $year,
                'fiscal_month' => $month,
            ],
            [
                'planned_hours' => $plannedHours,
                'planned_cost_idr' => $plannedCost,
                'week1_planned_hours' => $w1,
                'week2_planned_hours' => $w2,
                'week3_planned_hours' => $w3,
                'week4_planned_hours' => $w4,
                'week5_planned_hours' => $w5,
            ]
        );
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
    public function parseAndValidateCsv(UploadedFile|string $file, User $user): array
    {
        $cleanupTemp = false;
        if ($file instanceof UploadedFile) {
            $filePath = $file->getRealPath();
        } elseif (is_string($file) && file_exists($file)) {
            $filePath = $file;
        } elseif (is_string($file)) {
            $temp = (string) tempnam(sys_get_temp_dir(), 'budget_csv_');
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
        $header[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', (string) $header[0]);
        $normalizedHeader = array_map(fn ($col) => strtolower(trim((string) $col)), $header);

        $expectedColumns = ['section_code', 'fiscal_year', 'fiscal_month', 'planned_hours'];
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

        // Preload active sections with their departments for O(1) in-memory lookup
        $sections = Section::with('department')->get()->keyBy(fn ($s) => strtoupper($s->code));

        $parsedRows = [];
        $rowNumber = 1; // 1 is header
        $validCount = 0;
        $errorCount = 0;

        while (($data = fgetcsv($handle)) !== false) {
            $rowNumber++;

            // Skip empty rows
            if (empty(array_filter($data, fn ($val) => trim((string) $val) !== ''))) {
                continue;
            }

            $errors = [];

            $sectionCodeRaw = trim((string) ($data[$colIndices['section_code']] ?? ''));
            $sectionCode = strtoupper($sectionCodeRaw);
            $yearRaw = trim((string) ($data[$colIndices['fiscal_year']] ?? ''));
            $monthRaw = trim((string) ($data[$colIndices['fiscal_month']] ?? ''));
            $hoursRaw = trim((string) ($data[$colIndices['planned_hours']] ?? ''));

            $section = $sections->get($sectionCode);

            if ($sectionCode === '') {
                $errors[] = __('Line :row: Section code cannot be empty.', ['row' => $rowNumber]);
            } elseif (! $section) {
                $errors[] = __('Line :row: Section code :code not found in system.', ['row' => $rowNumber, 'code' => $sectionCodeRaw]);
            } elseif (! $section->is_active) {
                $errors[] = __('Line :row: Section :code is inactive.', ['row' => $rowNumber, 'code' => $sectionCodeRaw]);
            } elseif ($user->isManager() && (int) $section->department_id !== (int) $user->department_id) {
                $errors[] = __('Line :row: Section :code does not belong to your department.', ['row' => $rowNumber, 'code' => $sectionCodeRaw]);
            }

            $year = filter_var($yearRaw, FILTER_VALIDATE_INT);
            if ($year === false || $year < 2020 || $year > 2050) {
                $errors[] = __('Line :row: Fiscal year must be a valid number between 2020 and 2050.', ['row' => $rowNumber]);
            }

            $month = filter_var($monthRaw, FILTER_VALIDATE_INT);
            if ($month === false || $month < 1 || $month > 12) {
                $errors[] = __('Line :row: Fiscal month must be a number between 1 and 12.', ['row' => $rowNumber]);
            }

            $hours = is_numeric($hoursRaw) ? (float) $hoursRaw : null;
            if ($hours === null || $hours < 0 || $hours > 10000) {
                $errors[] = __('Line :row: Overtime hours must be a positive number (max 10,000).', ['row' => $rowNumber]);
            }

            $isValid = empty($errors);
            if ($isValid) {
                $validCount++;
            } else {
                $errorCount++;
            }

            $hourlyRate = $section?->department ? (float) $section->department->default_hourly_rate : 0.0;
            $estimatedCost = $hours !== null ? round($hours * $hourlyRate, 2) : 0.0;

            $parsedRows[] = [
                'line_number' => $rowNumber,
                'section_id' => $section?->id,
                'section_code' => $sectionCodeRaw,
                'section_name' => $section?->name ?? '-',
                'department_id' => $section?->department_id,
                'department_code' => $section?->department?->code ?? '-',
                'department_name' => $section?->department?->name ?? '-',
                'fiscal_year' => $year ?: 2026,
                'fiscal_month' => $month ?: 1,
                'planned_hours' => $hours !== null ? round($hours, 2) : 0.0,
                'planned_cost_idr' => $estimatedCost,
                'is_valid' => $isValid,
                'errors' => $errors,
            ];
        }

        fclose($handle);
        if ($cleanupTemp && file_exists($filePath)) {
            unlink($filePath);
        }

        return [
            'total' => count($parsedRows),
            'valid_count' => $validCount,
            'error_count' => $errorCount,
            'rows' => $parsedRows,
        ];
    }

    /**
     * Commit pre-validated CSV rows to the database.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{imported: int, errors: list<string>}
     */
    public function importRows(array $rows, User $user): array
    {
        $imported = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                $sectionId = (int) ($row['section_id'] ?? 0);
                $deptId = (int) ($row['department_id'] ?? 0);
                $year = (int) ($row['fiscal_year'] ?? 0);
                $month = (int) ($row['fiscal_month'] ?? 0);
                $hours = round((float) ($row['planned_hours'] ?? 0), 2);

                if (! $sectionId || ! $deptId || $year < 2020 || $month < 1 || $month > 12) {
                    $errors[] = __('Line :idx: Incomplete data or invalid format.', ['idx' => $index + 1]);

                    continue;
                }

                // Authorization check
                if ($user->isManager() && $deptId !== (int) $user->department_id) {
                    $errors[] = __('Line :idx: Not authorized to configure budget outside your department.', ['idx' => $index + 1]);

                    continue;
                }

                $department = Department::find($deptId);
                $hourlyRate = $department ? (float) $department->default_hourly_rate : 0.0;
                $plannedCost = round($hours * $hourlyRate, 2);

                // Auto 5-week distribution
                $weeklyAvg = round($hours / 4.3, 2);
                $w1 = $weeklyAvg;
                $w2 = $weeklyAvg;
                $w3 = $weeklyAvg;
                $w4 = $weeklyAvg;
                $w5 = round(max(0, $hours - ($weeklyAvg * 4)), 2);

                OvertimeBudget::updateOrCreate(
                    [
                        'department_id' => $deptId,
                        'section_id' => $sectionId,
                        'fiscal_year' => $year,
                        'fiscal_month' => $month,
                    ],
                    [
                        'planned_hours' => $hours,
                        'planned_cost_idr' => $plannedCost,
                        'week1_planned_hours' => $w1,
                        'week2_planned_hours' => $w2,
                        'week3_planned_hours' => $w3,
                        'week4_planned_hours' => $w4,
                        'week5_planned_hours' => $w5,
                    ]
                );

                $imported++;
            }

            if (! empty($errors) && $imported === 0) {
                DB::rollBack();

                return ['imported' => 0, 'errors' => $errors];
            }

            DB::commit();

            return ['imported' => $imported, 'errors' => $errors];
        } catch (\Throwable $e) {
            DB::rollBack();

            return ['imported' => 0, 'errors' => [$e->getMessage()]];
        }
    }

    /**
     * Generate standard CSV import template.
     */
    public function generateCsvTemplate(): string
    {
        $headers = ['section_code', 'fiscal_year', 'fiscal_month', 'planned_hours'];

        $sampleRows = [
            ['SEC_ASY_TRIM', '2026', '9', '120.00'],
            ['SEC_STP_PRESS', '2026', '9', '160.00'],
        ];

        $output = fopen('php://temp', 'r+');
        if (! $output) {
            return implode(',', $headers)."\n";
        }

        fputcsv($output, $headers);
        foreach ($sampleRows as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content !== false ? $content : implode(',', $headers)."\n";
    }
}
