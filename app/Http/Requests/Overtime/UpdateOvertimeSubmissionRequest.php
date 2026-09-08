<?php

namespace App\Http\Requests\Overtime;

use App\Models\CapexProject;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateOvertimeSubmissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        if (! $user) {
            return false;
        }

        /** @var OvertimeSubmission|int|string|null $submissionRouteParam */
        $submissionRouteParam = $this->route('submission');
        $submission = $submissionRouteParam instanceof OvertimeSubmission
            ? $submissionRouteParam
            : OvertimeSubmission::find($submissionRouteParam);

        if (! $submission) {
            return false;
        }

        // Guard edit: submissions that are APPROVED or PARTIALLY_APPROVED or contain approved items cannot be edited
        if (in_array($submission->status, ['APPROVED', 'PARTIALLY_APPROVED'], true) || $submission->items()->where('status', 'APPROVED')->exists()) {
            abort(422, __('Pengajuan ini memuat item yang sudah disetujui dan tidak dapat diubah.'));
        }

        // Verify user has access to existing submission's section
        if (! $user->canAccessSection($submission->section_id)) {
            return false;
        }

        $targetSectionId = (int) $this->input('section_id', $submission->section_id);

        return $user->canAccessSection($targetSectionId);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'operational_date' => ['required', 'date_format:Y-m-d'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'day_type' => ['nullable', 'string', 'in:HKN,HLR'],
            'submission_notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.employee_id' => ['required', 'integer', 'distinct', 'exists:employees,id'],
            'items.*.hours_production' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'items.*.hours_tpm' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'items.*.hours_project' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'items.*.hours_others' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'items.*.capex_project_id' => ['nullable', 'integer', 'exists:capex_projects,id'],
            'items.*.rca_category' => [
                'nullable',
                'string',
                'in:MACHINE_BREAKDOWN,SUPPLIER_DELAY,QUALITY_REWORK,CUSTOMER_RUSH,TRIAL_MODEL,FACILITY_MAINTENANCE,OTHER',
            ],
            'items.*.rca_notes' => ['nullable', 'string', 'max:1000'],
            'items.*.task_description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Configure the validator instance with business rule checks.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $departmentId = (int) $this->input('department_id');
            $sectionId = (int) $this->input('section_id');

            // Verify section belongs to department
            if ($sectionId && $departmentId) {
                $section = Section::find($sectionId);
                if (! $section || (int) $section->department_id !== $departmentId) {
                    $v->errors()->add('section_id', __('Seksi yang dipilih bukan merupakan bagian dari Departemen yang dipilih.'));
                }
            }

            // Verify items adhere to BR-01 (min 0.5h) and BR-08 (CapEx project attribution)
            $items = $this->input('items', []);
            if (is_array($items)) {
                foreach ($items as $index => $item) {
                    $prod = (float) ($item['hours_production'] ?? 0);
                    $tpm = (float) ($item['hours_tpm'] ?? 0);
                    $proj = (float) ($item['hours_project'] ?? 0);
                    $oth = (float) ($item['hours_others'] ?? 0);
                    $total = $prod + $tpm + $proj + $oth;

                    // BR-01: Each worker must have at least 0.5 total hours
                    if ($total < 0.50) {
                        $v->errors()->add(
                            "items.{$index}.hours_production",
                            __('Total jam lembur untuk karyawan ini minimal 0.5 jam sesuai aturan (BR-01).')
                        );
                    }

                    // BR-08: CapEx project required and active when hours_project > 0
                    if ($proj > 0.00) {
                        if (empty($item['capex_project_id'])) {
                            $v->errors()->add(
                                "items.{$index}.capex_project_id",
                                __('Proyek CapEx wajib dipilih jika jam lembur proyek diisi (BR-08).')
                            );
                        } else {
                            $capexProject = CapexProject::where('id', $item['capex_project_id'])
                                ->where('status', 'ACTIVE')
                                ->first();

                            if (! $capexProject) {
                                $v->errors()->add(
                                    "items.{$index}.capex_project_id",
                                    __('Proyek CapEx yang dipilih tidak aktif atau tidak ditemukan.')
                                );
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'operational_date.required' => __('Tanggal operasional wajib diisi.'),
            'department_id.required' => __('Departemen wajib dipilih.'),
            'section_id.required' => __('Seksi wajib dipilih.'),
            'items.required' => __('Minimal satu baris lembur karyawan wajib diisi.'),
            'items.min' => __('Minimal satu baris lembur karyawan wajib diisi.'),
            'items.*.employee_id.distinct' => __('Karyawan tidak boleh dimasukkan lebih dari satu kali dalam pengajuan yang sama.'),
        ];
    }
}
