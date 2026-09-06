<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Hash::make('password');

        $deptAsy = Department::where('code', 'DEPT_ASY')->first();
        $deptStp = Department::where('code', 'DEPT_STP')->first();
        $deptWld = Department::where('code', 'DEPT_WLD')->first();
        $deptPnt = Department::where('code', 'DEPT_PNT')->first();
        $deptMnt = Department::where('code', 'DEPT_MNT')->first();

        $secStpPress = Section::where('code', 'SEC_STP_PRESS')->first();
        $secWldUnder = Section::where('code', 'SEC_WLD_UNDER')->first();
        $secPntTop = Section::where('code', 'SEC_PNT_TOP')->first();
        $secAsyTrim = Section::where('code', 'SEC_ASY_TRIM')->first();
        $secMntElec = Section::where('code', 'SEC_MNT_ELEC')->first();

        // 1. Admin (1 demo account)
        User::updateOrCreate(
            ['email' => 'admin@factory.com'],
            [
                'name' => 'Super Administrator',
                'password' => $password,
                'role' => UserRole::Admin,
                'npk' => 'EMP-00001',
                'department_id' => null,
                'section_id' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        // 2. Managers (2 demo accounts)
        User::updateOrCreate(
            ['email' => 'manager.assembly@factory.com'],
            [
                'name' => 'Ir. Bambang Soeprapto',
                'password' => $password,
                'role' => UserRole::Manager,
                'npk' => 'EMP-00101',
                'department_id' => $deptAsy?->id,
                'section_id' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        User::updateOrCreate(
            ['email' => 'manager.stamping@factory.com'],
            [
                'name' => 'Dra. Sri Mulyani',
                'password' => $password,
                'role' => UserRole::Manager,
                'npk' => 'EMP-00102',
                'department_id' => $deptStp?->id,
                'section_id' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        // 3. Team Leaders (5 demo accounts)
        $teamLeaders = [
            [
                'name' => 'Ahmad Fauzi',
                'email' => 'tl.stamping.press@factory.com',
                'npk' => 'EMP-00201',
                'department_id' => $deptStp?->id,
                'section_id' => $secStpPress?->id,
            ],
            [
                'name' => 'Agus Setiawan',
                'email' => 'tl.welding.under@factory.com',
                'npk' => 'EMP-00202',
                'department_id' => $deptWld?->id,
                'section_id' => $secWldUnder?->id,
            ],
            [
                'name' => 'Hadi Pranoto',
                'email' => 'tl.painting.topcoat@factory.com',
                'npk' => 'EMP-00203',
                'department_id' => $deptPnt?->id,
                'section_id' => $secPntTop?->id,
            ],
            [
                'name' => 'Budi Santoso',
                'email' => 'tl.assembly.trim@factory.com',
                'npk' => 'EMP-00204',
                'department_id' => $deptAsy?->id,
                'section_id' => $secAsyTrim?->id,
            ],
            [
                'name' => 'Eko Prasetyo',
                'email' => 'tl.maintenance.elec@factory.com',
                'npk' => 'EMP-00205',
                'department_id' => $deptMnt?->id,
                'section_id' => $secMntElec?->id,
            ],
        ];

        foreach ($teamLeaders as $tl) {
            User::updateOrCreate(
                ['email' => $tl['email']],
                [
                    'name' => $tl['name'],
                    'password' => $password,
                    'role' => UserRole::TeamLeader,
                    'npk' => $tl['npk'],
                    'department_id' => $tl['department_id'],
                    'section_id' => $tl['section_id'],
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
        }

        // 4. Users / Operators (20 demo accounts)
        // Link to existing employees if available, or generate standard operator accounts
        $employees = Employee::where('job_position', '!=', 'Team Leader')->limit(20)->get();

        for ($i = 1; $i <= 20; $i++) {
            $emp = $employees->get($i - 1);
            $email = sprintf('operator.%02d@factory.com', $i);
            $npk = $emp?->npk ?? sprintf('EMP-%05d', 2000 + $i);
            $name = $emp?->full_name ?? "Operator Line {$i}";
            $deptId = $emp?->department_id ?? $deptAsy?->id;
            $secId = $emp?->section_id ?? $secAsyTrim?->id;

            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => $password,
                    'role' => UserRole::User,
                    'npk' => $npk,
                    'department_id' => $deptId,
                    'section_id' => $secId,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
