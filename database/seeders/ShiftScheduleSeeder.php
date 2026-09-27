<?php

namespace Database\Seeders;

use App\Models\ShiftSchedule;
use Illuminate\Database\Seeder;

class ShiftScheduleSeeder extends Seeder
{
    /**
     * Seed the default plant shift schedule.
     */
    public function run(): void
    {
        if (ShiftSchedule::query()->exists()) {
            return;
        }

        ShiftSchedule::query()->create([
            'name' => 'Standard Plant Shift',
            'effective_from' => now('Asia/Jakarta')->startOfYear()->toDateString(),
            'effective_until' => null,
            'shift_1_start' => '07:00',
            'shift_1_end' => '15:00',
            'shift_2_start' => '15:00',
            'shift_2_end' => '23:00',
            'shift_3_start' => '23:00',
            'shift_3_end' => '07:00',
        ]);
    }
}
