<?php

namespace Database\Seeders;

use App\Enums\AlertCause;
use App\Models\AlertCausePicMapping;
use App\Models\Department;
use Illuminate\Database\Seeder;

class AlertCausePicMappingSeeder extends Seeder
{
    /**
     * Seed one mapping row per cause with the default PIC department (by department code) when it exists.
     *
     * A cause without a default, or whose department is missing, stays unmapped and falls back to the
     * alert's own department. Existing rows (possibly edited by an admin) are never overwritten.
     */
    public function run(): void
    {
        $departmentIds = Department::query()->pluck('id', 'code');

        foreach (AlertCause::cases() as $cause) {
            $code = $cause->defaultPicKey();

            AlertCausePicMapping::query()->firstOrCreate(
                ['cause' => $cause->value],
                [
                    'pic_department_id' => $code !== null ? ($departmentIds[$code] ?? null) : null,
                    'is_active' => true,
                ],
            );
        }
    }
}
