<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Section;
use App\Models\SplEntry;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the real PCD (Planning, Control & Delivery) employees from db_pegawai in ot_excel.xlsx,
 * creates a PCD manager user and team leader, then re-links existing spl_entries.
 *
 * Safe to run multiple times (uses upsert / updateOrCreate).
 */
class PcdEmployeeSeeder extends Seeder
{
    /**
     * Real PCD employee data sourced from db_pegawai sheet in ot_excel.xlsx.
     * These NPKs appear in the client's actual spl_manual_ot.xlsx imports.
     *
     * @var list<array{npk: string, full_name: string, job_position: string}>
     */
    private array $pcdEmployees = [
        ['npk' => '10703', 'full_name' => 'TIAN PERMATA SARI',          'job_position' => 'Staff PCD'],
        ['npk' => '58065', 'full_name' => 'DWIE MURIATI',                'job_position' => 'Staff PCD'],
        ['npk' => '17177', 'full_name' => 'KARLINA IBRAHIM',             'job_position' => 'Staff PCD'],
        ['npk' => '33824', 'full_name' => 'SIGIT PURNOMO',               'job_position' => 'Staff PCD'],
        ['npk' => '35797', 'full_name' => 'IMAM HUSNI',                  'job_position' => 'Staff PCD'],
        ['npk' => '6740',  'full_name' => 'RICKY DWIJAYANTO',            'job_position' => 'Staff PCD'],
        ['npk' => '35741', 'full_name' => 'ERVAN AJI',                   'job_position' => 'Staff PCD'],
        ['npk' => '58375', 'full_name' => 'AMIRULLAH',                   'job_position' => 'Staff PCD'],
        ['npk' => '35735', 'full_name' => 'HAIKAL FIHKRI MUKHLISIN',     'job_position' => 'Staff PCD'],
        ['npk' => '5584',  'full_name' => 'HENDRI',                      'job_position' => 'Staff PCD'],
        ['npk' => '50158', 'full_name' => 'JONNY FERRY N.',              'job_position' => 'Staff PCD'],
        ['npk' => '41463', 'full_name' => 'RIDWAN SUPRATMAN',            'job_position' => 'Staff PCD'],
        ['npk' => '35318', 'full_name' => 'MUHAMMAD RAMADHAN MAKAADO',   'job_position' => 'Staff PCD'],
        ['npk' => '58515', 'full_name' => 'DIDIK SETYAWAN',              'job_position' => 'Staff PCD'],
        ['npk' => '45397', 'full_name' => 'DATASET',                     'job_position' => 'Staff PCD'],
        ['npk' => '59280', 'full_name' => 'AGIT AGUSTIAN MAHENDRA',      'job_position' => 'Staff PCD'],
        ['npk' => '59281', 'full_name' => 'HARDIAN INDRA RUKMANA',       'job_position' => 'Staff PCD'],
    ];

    public function run(): void
    {
        $dept = Department::where('code', 'DEPT_PCD')->first();
        if (! $dept) {
            $this->command->warn('DEPT_PCD not found — run SectionSeeder/DepartmentSeeder first.');

            return;
        }

        $section = Section::where('department_id', $dept->id)->first();
        if (! $section) {
            $this->command->warn('No section found for DEPT_PCD.');

            return;
        }

        $this->command->info("Seeding PCD employees into section '{$section->name}' (dept {$dept->id}) …");

        $now = Carbon::now();
        $baseRate = (float) ($dept->default_hourly_rate ?? 45000);
        $rows = [];

        foreach ($this->pcdEmployees as $emp) {
            $rows[] = [
                'npk' => $emp['npk'],
                'department_id' => $dept->id,
                'section_id' => $section->id,
                'full_name' => $emp['full_name'],
                'job_position' => $emp['job_position'],
                'hourly_rate' => $baseRate,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Employee::upsert(
            $rows,
            ['npk'],
            ['department_id', 'section_id', 'full_name', 'job_position', 'hourly_rate', 'is_active', 'updated_at'],
        );
        $this->command->info('✓ '.count($rows).' PCD employees upserted.');

        // ── PCD Manager ────────────────────────────────────────────────────────
        $password = Hash::make('password');
        User::updateOrCreate(
            ['email' => 'manager.pcd@factory.com'],
            [
                'name' => 'Manajer PCD',
                'password' => $password,
                'role' => UserRole::Manager,
                'npk' => 'EMP-00103',
                'department_id' => $dept->id,
                'section_id' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
        $this->command->info('✓ Manager user created: manager.pcd@factory.com (password)');

        // ── PCD Team Leader ────────────────────────────────────────────────────
        User::updateOrCreate(
            ['email' => 'tl.pcd@factory.com'],
            [
                'name' => 'Team Leader PCD',
                'password' => $password,
                'role' => UserRole::TeamLeader,
                'npk' => 'EMP-00210',
                'department_id' => $dept->id,
                'section_id' => $section->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
        $this->command->info('✓ Team leader created: tl.pcd@factory.com (password)');

        // ── Re-link spl_entries.employee_id ────────────────────────────────────
        $this->command->info('Re-linking spl_entries.employee_id …');
        $updated = 0;

        $employees = Employee::where('department_id', $dept->id)
            ->whereIn('npk', collect($this->pcdEmployees)->pluck('npk'))
            ->get()
            ->keyBy('npk');

        SplEntry::whereNull('employee_id')
            ->whereNotNull('npk_snapshot')
            ->chunk(200, function ($entries) use ($employees, &$updated): void {
                foreach ($entries as $entry) {
                    $emp = $employees->get((string) $entry->npk_snapshot);
                    if ($emp) {
                        $entry->update(['employee_id' => $emp->id]);
                        $updated++;
                    }
                }
            });

        // Also update name/section snapshots where section_id or name is missing
        SplEntry::where('section_id', $section->id)
            ->where(fn ($q) => $q->whereNull('section_name_snapshot')->orWhere('section_name_snapshot', ''))
            ->update([
                'section_name_snapshot' => $section->name,
                'department_name_snapshot' => $dept->name,
            ]);

        $this->command->info("✓ Re-linked {$updated} spl_entries to employee records.");
    }
}
