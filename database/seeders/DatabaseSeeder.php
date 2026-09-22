<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            SectionSeeder::class,
            EmployeeSeeder::class,
            PcdEmployeeSeeder::class,   // Seeds real PCD employees from ot_excel.xlsx db_pegawai
            UserSeeder::class,
            OperationalCalendarSeeder::class,
            PolicyThresholdSeeder::class,
            OvertimeBudgetSeeder::class,
            DummyDataSeeder::class,
            Ytd2026DummyDataSeeder::class,
        ]);
    }
}
