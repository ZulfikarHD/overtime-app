<?php

namespace App\Actions\Overtime;

use App\Jobs\RunAnomalyDetectionJob;
use App\Models\CapexProject;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Services\PolicyThresholdService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitOvertimeAction
{
    public function __construct(
        public PolicyThresholdService $policyThresholdService,
    ) {}

    /**
     * Executes atomic batch overtime submission with snapshot isolation.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function execute(array $data, int $userId): OvertimeSubmission
    {
        return DB::transaction(function () use ($data, $userId) {
            $operationalDate = Carbon::parse($data['operational_date'])->format('Y-m-d');

            // 1. Resolve Day Classification (HKN vs HLR) and ensure calendar record exists
            $calendar = OperationalCalendar::find($operationalDate)
                ?? OperationalCalendar::whereDate('calendar_date', $operationalDate)->first();
            if (! $calendar) {
                $calendar = OperationalCalendar::create([
                    'calendar_date' => $operationalDate,
                    'day_type' => Carbon::parse($operationalDate)->isWeekend() ? 'HLR' : 'HKN',
                    'is_holiday' => false,
                ]);
            }

            $dayType = ! empty($data['day_type']) && in_array($data['day_type'], ['HKN', 'HLR'], true)
                ? $data['day_type']
                : $calendar->day_type;

            // 2. Lock & Validate Department, Section, and Roster Integrity
            $department = Department::findOrFail($data['department_id']);
            $section = Section::where('id', $data['section_id'])
                ->where('department_id', $department->id)
                ->firstOrFail();

            $itemsData = $data['items'] ?? [];
            if (empty($itemsData)) {
                throw ValidationException::withMessages([
                    'items' => [__('At least one employee overtime row is required.')],
                ]);
            }

            $employeeIds = collect($itemsData)->pluck('employee_id')->all();

            $roster = Employee::whereIn('id', $employeeIds)
                ->where('department_id', $department->id)
                ->where('section_id', $section->id)
                ->where('is_active', true)
                ->get()
                ->keyBy('id');

            if ($roster->count() !== count(array_unique($employeeIds))) {
                throw ValidationException::withMessages([
                    'items' => [__('One or more employees do not belong to the selected department/section or are inactive.')],
                ]);
            }

            // 3. Create Parent Submission Header
            $dateStr = Carbon::parse($operationalDate)->format('Ymd');
            $secCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $section->code));
            $sequence = OvertimeSubmission::whereDate('operational_date', $operationalDate)
                ->where('section_id', $section->id)
                ->count() + 1;
            $submissionCode = sprintf('OT-%s-%s-%03d', $dateStr, $secCode, $sequence);

            while (OvertimeSubmission::where('submission_code', $submissionCode)->exists()) {
                $sequence++;
                $submissionCode = sprintf('OT-%s-%s-%03d', $dateStr, $secCode, $sequence);
            }

            $submission = OvertimeSubmission::create([
                'submission_code' => $submissionCode,
                'submission_date' => now('Asia/Jakarta')->toDateString(),
                'operational_date' => $operationalDate,
                'day_type' => $dayType,
                'department_id' => $department->id,
                'section_id' => $section->id,
                'submitted_by_user_id' => $userId,
                'status' => 'SUBMITTED',
                'submission_notes' => $data['submission_notes'] ?? ($data['notes'] ?? null),
            ]);

            // 4. Create Linked Non-Blocking SPKL Container
            $threshold = $this->policyThresholdService->getForDepartment($department->id);
            $graceDays = (int) $threshold->spkl_grace_period_days;

            $submission->spklDocument()->create([
                'status' => 'PENDING',
                'due_date' => Carbon::parse($operationalDate)->addWeekdays($graceDays)->toDateString(),
            ]);

            // 5. Insert Child Items & Snapshot Financial Costs
            $totalHoursAccumulator = '0.00';

            foreach ($itemsData as $itemData) {
                /** @var Employee $employee */
                $employee = $roster[$itemData['employee_id']];

                $prod = round((float) ($itemData['hours_production'] ?? 0), 2);
                $tpm = round((float) ($itemData['hours_tpm'] ?? 0), 2);
                $proj = round((float) ($itemData['hours_project'] ?? 0), 2);
                $oth = round((float) ($itemData['hours_others'] ?? 0), 2);
                $lineTotal = $prod + $tpm + $proj + $oth;

                // Validate BR-01: minimum 0.5 hours
                if ($lineTotal < 0.50) {
                    throw ValidationException::withMessages([
                        'items' => [__('Total jam lembur untuk :name minimal 0.5 jam sesuai aturan (BR-01).', ['name' => $employee->full_name])],
                    ]);
                }

                // Validate BR-08: CapEx project required when hours_project > 0
                $capexProjectId = null;
                if ($proj > 0.00) {
                    if (empty($itemData['capex_project_id'])) {
                        throw ValidationException::withMessages([
                            'items' => [__('Jam lembur proyek CapEx untuk :name memerlukan pemilihan Proyek Investasi (BR-08).', ['name' => $employee->full_name])],
                        ]);
                    }
                    $capexProject = CapexProject::where('id', $itemData['capex_project_id'])
                        ->where('status', 'ACTIVE')
                        ->first();
                    if (! $capexProject) {
                        throw ValidationException::withMessages([
                            'items' => [__('Proyek CapEx yang dipilih tidak aktif atau tidak ditemukan.')],
                        ]);
                    }
                    $capexProjectId = $capexProject->id;
                }

                // Immutable Financial Snapshotting
                $rateSnapshot = ($employee->hourly_rate !== null && (float) $employee->hourly_rate > 0)
                    ? (string) $employee->hourly_rate
                    : (string) ($department->default_hourly_rate ?? 0.00);

                $costSnapshot = bcmul((string) $lineTotal, (string) $rateSnapshot, 2);

                $createdItem = OvertimeItem::create([
                    'overtime_submission_id' => $submission->id,
                    'employee_id' => $employee->id,
                    'npk_snapshot' => $employee->npk,
                    'capex_project_id' => $capexProjectId,
                    'hours_production' => $prod,
                    'hours_tpm' => $tpm,
                    'hours_project' => $proj,
                    'hours_others' => $oth,
                    'hourly_rate_snapshot' => $rateSnapshot,
                    'total_cost_snapshot' => $costSnapshot,
                    'rca_category' => $itemData['rca_category'] ?? null,
                    'rca_notes' => $itemData['rca_notes'] ?? null,
                    'task_description' => $itemData['task_description'] ?? null,
                    'status' => 'PENDING',
                    'lock_version' => 1,
                ]);

                $totalHoursAccumulator = bcadd($totalHoursAccumulator, (string) $lineTotal, 2);

                // Dispatch Asynchronous ML Anomaly Detection Scan
                RunAnomalyDetectionJob::dispatch($createdItem->id);
            }

            // 6. Update Cached Header Total
            $submission->update(['total_hours_cached' => $totalHoursAccumulator]);

            return $submission->load(['items.employee', 'items.capexProject', 'spklDocument', 'department', 'section']);
        });
    }
}
