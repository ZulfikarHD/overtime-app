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

        $deptProd = Department::where('code', 'DEPT_PROD')->first();
        $deptPcd = Department::where('code', 'DEPT_PCD')->first();
        $deptQc = Department::where('code', 'DEPT_QC')->first();

        $secBodyNsA = Section::where('code', 'SEC_PROD_BODY_NS_A')->first();
        $secPaintA = Section::where('code', 'SEC_PROD_PAINT_A')->first();
        $secTcfNsA = Section::where('code', 'SEC_PROD_TCF_NS_A')->first();
        $secPcd = Section::where('code', 'SEC_PCD_MAIN')->first();
        $secQc = Section::where('code', 'SEC_QC_MAIN')->first();

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
            ['email' => 'manager.production@factory.com'],
            [
                'name' => 'Ir. Bambang Soeprapto',
                'password' => $password,
                'role' => UserRole::Manager,
                'npk' => 'EMP-00101',
                'department_id' => $deptProd?->id,
                'section_id' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        User::updateOrCreate(
            ['email' => 'manager.pcd@factory.com'],
            [
                'name' => 'Dra. Sri Mulyani',
                'password' => $password,
                'role' => UserRole::Manager,
                'npk' => 'EMP-00102',
                'department_id' => $deptPcd?->id,
                'section_id' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        // 3. Team Leaders (5 demo accounts)
        $teamLeaders = [
            [
                'name' => 'Ahmad Fauzi',
                'email' => 'tl.prod.body.ns.a@factory.com',
                'npk' => 'EMP-00201',
                'department_id' => $deptProd?->id,
                'section_id' => $secBodyNsA?->id,
            ],
            [
                'name' => 'Agus Setiawan',
                'email' => 'tl.prod.paint.a@factory.com',
                'npk' => 'EMP-00202',
                'department_id' => $deptProd?->id,
                'section_id' => $secPaintA?->id,
            ],
            [
                'name' => 'Hadi Pranoto',
                'email' => 'tl.prod.tcf.ns.a@factory.com',
                'npk' => 'EMP-00203',
                'department_id' => $deptProd?->id,
                'section_id' => $secTcfNsA?->id,
            ],
            [
                'name' => 'Budi Santoso',
                'email' => 'tl.pcd@factory.com',
                'npk' => 'EMP-00204',
                'department_id' => $deptPcd?->id,
                'section_id' => $secPcd?->id,
            ],
            [
                'name' => 'Eko Prasetyo',
                'email' => 'tl.qc@factory.com',
                'npk' => 'EMP-00205',
                'department_id' => $deptQc?->id,
                'section_id' => $secQc?->id,
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
            $deptId = $emp?->department_id ?? $deptProd?->id;
            $secId = $emp?->section_id ?? $secBodyNsA?->id;

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
