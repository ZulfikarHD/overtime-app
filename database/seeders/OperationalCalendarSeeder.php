<?php

namespace Database\Seeders;

use App\Models\OperationalCalendar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class OperationalCalendarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $year = 2026;
        $startDate = Carbon::createFromDate($year, 1, 1);
        $endDate = Carbon::createFromDate($year, 12, 31);

        $holidays = [
            '2026-01-01' => 'Tahun Baru 2026 Masehi',
            '2026-01-16' => "Isra Mi'raj Nabi Muhammad SAW",
            '2026-02-17' => 'Tahun Baru Imlek 2577 Kongzili',
            '2026-03-20' => 'Hari Suci Nyepi (Tahun Baru Saka 1948)',
            '2026-03-21' => 'Hari Raya Idul Fitri 1447 H (Hari ke-1)',
            '2026-03-22' => 'Hari Raya Idul Fitri 1447 H (Hari ke-2)',
            '2026-04-03' => 'Wafat Yesus Kristus',
            '2026-05-01' => 'Hari Buruh Internasional',
            '2026-05-14' => 'Kenaikan Yesus Kristus',
            '2026-05-27' => 'Hari Raya Idul Adha 1447 H',
            '2026-05-31' => 'Hari Raya Waisak 2570 BE',
            '2026-06-01' => 'Hari Lahir Pancasila',
            '2026-06-16' => 'Tahun Baru Islam 1448 H',
            '2026-08-17' => 'Hari Kemerdekaan RI ke-81',
            '2026-08-25' => 'Maulid Nabi Muhammad SAW',
            '2026-12-25' => 'Hari Raya Natal',
        ];

        $records = [];
        $current = $startDate->copy();
        $now = Carbon::now();

        while ($current->lte($endDate)) {
            $dateStr = $current->format('Y-m-d');
            $isWeekend = $current->isWeekend(); // Saturday or Sunday
            $isPublicHoliday = isset($holidays[$dateStr]);

            $isHoliday = $isWeekend || $isPublicHoliday;
            $dayType = $isHoliday ? 'HLR' : 'HKN';
            $holidayName = $holidays[$dateStr] ?? ($isWeekend ? ($current->isSaturday() ? 'Sabtu Libur' : 'Minggu Libur') : null);
            $description = $isPublicHoliday ? "Hari Libur Nasional: {$holidayName}" : ($isWeekend ? 'Akhir Pekan' : 'Hari Kerja Normal');

            $records[] = [
                'calendar_date' => $dateStr,
                'day_type' => $dayType,
                'is_holiday' => $isHoliday,
                'holiday_name' => $holidayName,
                'description' => $description,
                'created_at' => $now,
            ];

            $current->addDay();
        }

        foreach (array_chunk($records, 100) as $chunk) {
            OperationalCalendar::upsert(
                $chunk,
                ['calendar_date'],
                ['day_type', 'is_holiday', 'holiday_name', 'description'],
            );
        }
    }
}
