<?php

namespace App\Services;

use App\Enums\AlertCause;
use App\Models\AlertCausePicMapping;
use App\Models\Department;

/**
 * Admin-editable "cause → PIC department" mapping of the Early Warning follow-up generator (E10-08).
 *
 * Changes only affect suggestions generated afterwards; a saved or sent follow-up keeps the text and the
 * snapshot (`generated_payload`) it was created with.
 */
class AlertCauseMappingService
{
    /**
     * One row per cause for the Administration card, including causes without a saved mapping.
     *
     * A cause without a saved row shows the configured default department (when it exists), which is exactly
     * what the generator would use, so the card never lies about the effective PIC.
     *
     * @return list<array{cause: string, label: string, description: string, pic_department_id: ?int, is_active: bool, is_default: bool}>
     */
    public function listForAdmin(): array
    {
        $rows = AlertCausePicMapping::query()->get()->keyBy(fn (AlertCausePicMapping $row): string => $row->cause->value);
        $departmentIds = Department::query()->pluck('id', 'code');

        return collect(AlertCause::cases())
            ->map(function (AlertCause $cause) use ($rows, $departmentIds): array {
                $row = $rows->get($cause->value);
                $defaultCode = $cause->defaultPicKey();

                return [
                    'cause' => $cause->value,
                    'label' => __($cause->label()),
                    'description' => __($cause->description()),
                    'pic_department_id' => $row !== null
                        ? $row->pic_department_id
                        : ($defaultCode !== null ? ($departmentIds[$defaultCode] ?? null) : null),
                    'is_active' => $row?->is_active ?? true,
                    'is_default' => $row === null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Save the mapping of the given causes (create or update one row per cause).
     *
     * @param  list<array{cause: string, pic_department_id: ?int, is_active: bool}>  $mappings
     */
    public function save(array $mappings): void
    {
        foreach ($mappings as $mapping) {
            AlertCausePicMapping::query()->updateOrCreate(
                ['cause' => $mapping['cause']],
                [
                    'pic_department_id' => $mapping['pic_department_id'] ?? null,
                    'is_active' => (bool) $mapping['is_active'],
                ],
            );
        }
    }
}
