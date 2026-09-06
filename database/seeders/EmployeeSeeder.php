<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Section;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sections = Section::with('department')->get();
        if ($sections->isEmpty()) {
            return;
        }

        $firstNames = [
            'Ahmad', 'Budi', 'Tri', 'Joko', 'Hendra', 'Agus', 'Hadi', 'Eko',
            'Siti', 'Dewi', 'Rina', 'Mega', 'Rizky', 'Fajar', 'Dedi', 'Yusuf',
            'Aris', 'Bayu', 'Ilham', 'Wawan', 'Surya', 'Teguh', 'Bambang', 'Danang',
            'Putra', 'Dimas', 'Indra', 'Aditya', 'Guruh', 'Fauzan', 'Wahyu', 'Rahmat',
        ];

        $lastNames = [
            'Fauzi', 'Santoso', 'Wahyudi', 'Susilo', 'Gunawan', 'Setiawan', 'Pranoto', 'Prasetyo',
            'Lestari', 'Kusuma', 'Nugroho', 'Saputra', 'Hidayat', 'Firmansyah', 'Hermawan', 'Utomo',
            'Wibowo', 'Pratama', 'Siregar', 'Harahap', 'Nasution', 'Kurniawan', 'Subekti', 'Hartanto',
        ];

        $positions = [
            'Team Leader',
            'Senior Line Operator',
            'Line Operator Grade 1',
            'Line Operator Grade 2',
            'Die & Mold Specialist',
            'Robot Welding Technician',
            'Automation Technician',
            'Quality Control Inspector',
        ];

        $npkCounter = 1001;
        $employees = [];
        $now = Carbon::now();

        foreach ($sections as $section) {
            // Seed 8 employees per section (15 sections * 8 = 120 employees)
            for ($i = 0; $i < 8; $i++) {
                $npk = sprintf('EMP-%05d', $npkCounter++);
                $fName = $firstNames[array_rand($firstNames)];
                $lName = $lastNames[array_rand($lastNames)];
                $fullName = "{$fName} {$lName}";

                $position = $i === 0 ? 'Team Leader' : $positions[array_rand($positions)];
                $baseRate = (float) ($section->department->default_hourly_rate ?? 45000);
                // Vary hourly rate based on seniority/position
                $rateMultiplier = $i === 0 ? 1.30 : (1.00 + (($i % 4) * 0.05));
                $hourlyRate = round($baseRate * $rateMultiplier, 2);

                $employees[] = [
                    'npk' => $npk,
                    'department_id' => $section->department_id,
                    'section_id' => $section->id,
                    'full_name' => $fullName,
                    'job_position' => $position,
                    'hourly_rate' => $hourlyRate,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Upsert in batches of 50 for optimal performance
        foreach (array_chunk($employees, 50) as $chunk) {
            Employee::upsert(
                $chunk,
                ['npk'],
                ['department_id', 'section_id', 'full_name', 'job_position', 'hourly_rate', 'is_active', 'updated_at'],
            );
        }
    }
}
