<?php

namespace App\Actions\Overtime;

use App\Jobs\RunAnomalyDetectionJob;
use App\Models\CapexProject;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeItemAudit;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Services\Policy\OvertimePolicyEvaluator;
use App\Services\PolicyThresholdService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitOvertimeAction
{
    public function __construct(
        public PolicyThresholdService $policyThresholdService,
        public OvertimePolicyEvaluator $policyEvaluator,
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

                $prodStr = number_format((float) ($itemData['hours_production'] ?? 0), 2, '.', '');
                $tpmStr = number_format((float) ($itemData['hours_tpm'] ?? 0), 2, '.', '');
                $projStr = number_format((float) ($itemData['hours_project'] ?? 0), 2, '.', '');
                $othStr = number_format((float) ($itemData['hours_others'] ?? 0), 2, '.', '');

                $lineTotalStr = bcadd(bcadd(bcadd($prodStr, $tpmStr, 2), $projStr, 2), $othStr, 2);

                // Validate BR-01: minimum 0.5 hours
                if (bccomp($lineTotalStr, '0.50', 2) < 0) {
                    throw ValidationException::withMessages([
                        'items' => [__('Total jam lembur untuk :name minimal 0.5 jam sesuai aturan (BR-01).', ['name' => $employee->full_name])],
                    ]);
                }

                // Validate BR-08: CapEx project required when hours_project > 0
                $capexProjectId = null;
                if (bccomp($projStr, '0.00', 2) > 0) {
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

                // Immutable Financial Snapshotting (E03-02)
                $rateSnapshot = ($employee->hourly_rate !== null)
                    ? number_format((float) $employee->hourly_rate, 2, '.', '')
                    : number_format((float) ($department->default_hourly_rate ?? 0.00), 2, '.', '');

                $costSnapshot = bcmul($lineTotalStr, $rateSnapshot, 2);

                $createdItem = OvertimeItem::create([
                    'overtime_submission_id' => $submission->id,
                    'employee_id' => $employee->id,
                    'npk_snapshot' => $employee->npk,
                    'capex_project_id' => $capexProjectId,
                    'hours_production' => (float) $prodStr,
                    'hours_tpm' => (float) $tpmStr,
                    'hours_project' => (float) $projStr,
                    'hours_others' => (float) $othStr,
                    'hourly_rate_snapshot' => $rateSnapshot,
                    'total_cost_snapshot' => $costSnapshot,
                    'rca_category' => $itemData['rca_category'] ?? null,
                    'rca_notes' => $itemData['rca_notes'] ?? null,
                    'task_description' => $itemData['task_description'] ?? null,
                    'status' => 'PENDING',
                    'lock_version' => 1,
                ]);

                $totalHoursAccumulator = bcadd($totalHoursAccumulator, $lineTotalStr, 2);

                // Synchronous Initial Audit Ledger Entry (E04-05)
                OvertimeItemAudit::create([
                    'overtime_item_id' => $createdItem->id,
                    'action' => 'SUBMITTED',
                    'actor_user_id' => $userId,
                    'previous_state' => null,
                    'new_state' => $createdItem->toArray(),
                    'notes' => 'Pengajuan lembur diserahkan.',
                    'ip_address' => request()?->ip(),
                    'created_at' => Carbon::now('Asia/Jakarta'),
                ]);

                // Evaluate policy soft warning (advisory only, BR-06)
                $policyWarning = $this->policyEvaluator->evaluateEmployee(
                    employeeId: $employee->id,
                    additionalHours: 0.0,
                    date: $operationalDate,
                );
                Cache::put("overtime_policy_warning:{$createdItem->id}", $policyWarning->toArray(), now()->addDays(7));
                $createdItem->setAttribute('policy_warning', $policyWarning->toArray());

                // Dispatch Asynchronous ML Anomaly Detection Scan
                RunAnomalyDetectionJob::dispatch($createdItem->id);
            }

            // 6. Update Cached Header Total
            $submission->update(['total_hours_cached' => $totalHoursAccumulator]);

            return $submission->load(['items.employee', 'items.capexProject', 'spklDocument', 'department', 'section']);
        });
    }

    /**
     * Executes atomic batch update on an existing overtime submission with fresh rate snapshots.
     * Guarded: Aborts with 422 if status is APPROVED or PARTIALLY_APPROVED.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(OvertimeSubmission $submission, array $data, int $userId): OvertimeSubmission
    {
        if (in_array($submission->status, ['APPROVED', 'PARTIALLY_APPROVED'], true)) {
            abort(422, __('Pengajuan yang sudah disetujui atau disetujui sebagian terkunci dan tidak dapat diedit.'));
        }

        return DB::transaction(function () use ($submission, $data, $userId) {
            $operationalDate = Carbon::parse($data['operational_date'])->format('Y-m-d');

            // 1. Resolve Day Classification (HKN vs HLR)
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

            // 3. Remove existing items
            $submission->items()->delete();

            // 4. Re-insert items with fresh financial snapshots
            $totalHoursAccumulator = '0.00';

            foreach ($itemsData as $itemData) {
                /** @var Employee $employee */
                $employee = $roster[$itemData['employee_id']];

                $prodStr = number_format((float) ($itemData['hours_production'] ?? 0), 2, '.', '');
                $tpmStr = number_format((float) ($itemData['hours_tpm'] ?? 0), 2, '.', '');
                $projStr = number_format((float) ($itemData['hours_project'] ?? 0), 2, '.', '');
                $othStr = number_format((float) ($itemData['hours_others'] ?? 0), 2, '.', '');

                $lineTotalStr = bcadd(bcadd(bcadd($prodStr, $tpmStr, 2), $projStr, 2), $othStr, 2);

                // Validate BR-01: minimum 0.5 hours
                if (bccomp($lineTotalStr, '0.50', 2) < 0) {
                    throw ValidationException::withMessages([
                        'items' => [__('Total jam lembur untuk :name minimal 0.5 jam sesuai aturan (BR-01).', ['name' => $employee->full_name])],
                    ]);
                }

                // Validate BR-08: CapEx project required when hours_project > 0
                $capexProjectId = null;
                if (bccomp($projStr, '0.00', 2) > 0) {
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

                // Fresh Financial Snapshotting (E03-02 / E03-03 re-snapshot)
                $rateSnapshot = ($employee->hourly_rate !== null)
                    ? number_format((float) $employee->hourly_rate, 2, '.', '')
                    : number_format((float) ($department->default_hourly_rate ?? 0.00), 2, '.', '');

                $costSnapshot = bcmul($lineTotalStr, $rateSnapshot, 2);

                $createdItem = OvertimeItem::create([
                    'overtime_submission_id' => $submission->id,
                    'employee_id' => $employee->id,
                    'npk_snapshot' => $employee->npk,
                    'capex_project_id' => $capexProjectId,
                    'hours_production' => (float) $prodStr,
                    'hours_tpm' => (float) $tpmStr,
                    'hours_project' => (float) $projStr,
                    'hours_others' => (float) $othStr,
                    'hourly_rate_snapshot' => $rateSnapshot,
                    'total_cost_snapshot' => $costSnapshot,
                    'rca_category' => $itemData['rca_category'] ?? null,
                    'rca_notes' => $itemData['rca_notes'] ?? null,
                    'task_description' => $itemData['task_description'] ?? null,
                    'status' => 'PENDING',
                    'lock_version' => 1,
                ]);

                $totalHoursAccumulator = bcadd($totalHoursAccumulator, $lineTotalStr, 2);

                // Synchronous Initial Audit Ledger Entry (E04-05)
                OvertimeItemAudit::create([
                    'overtime_item_id' => $createdItem->id,
                    'action' => 'SUBMITTED',
                    'actor_user_id' => $userId,
                    'previous_state' => null,
                    'new_state' => $createdItem->toArray(),
                    'notes' => 'Pengajuan lembur diperbarui dan diserahkan.',
                    'ip_address' => request()?->ip(),
                    'created_at' => Carbon::now('Asia/Jakarta'),
                ]);

                // Evaluate policy soft warning (advisory only, BR-06)
                $policyWarning = $this->policyEvaluator->evaluateEmployee(
                    employeeId: $employee->id,
                    additionalHours: 0.0,
                    date: $operationalDate,
                );
                Cache::put("overtime_policy_warning:{$createdItem->id}", $policyWarning->toArray(), now()->addDays(7));
                $createdItem->setAttribute('policy_warning', $policyWarning->toArray());

                RunAnomalyDetectionJob::dispatch($createdItem->id);
            }

            // 5. Update Submission Header
            $submission->update([
                'operational_date' => $operationalDate,
                'day_type' => $dayType,
                'department_id' => $department->id,
                'section_id' => $section->id,
                'submission_notes' => $data['submission_notes'] ?? ($data['notes'] ?? null),
                'total_hours_cached' => $totalHoursAccumulator,
                'status' => 'SUBMITTED',
            ]);

            // 6. Update SPKL due date if operational date changed
            if ($submission->spklDocument) {
                $threshold = $this->policyThresholdService->getForDepartment($department->id);
                $graceDays = (int) $threshold->spkl_grace_period_days;
                $submission->spklDocument->update([
                    'due_date' => Carbon::parse($operationalDate)->addWeekdays($graceDays)->toDateString(),
                ]);
            }

            return $submission->fresh()->load(['items.employee', 'items.capexProject', 'spklDocument', 'department', 'section', 'submittedBy']);
        });
    }
}
