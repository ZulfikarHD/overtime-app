<?php

namespace App\Console\Commands;

use App\Services\OperationalCalendarService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SeedOperationalCalendarCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:seed-calendar {year? : The calendar year to seed (default: current and next fiscal year)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed operational calendar (HKN workdays and HLR holidays) for a fiscal year or current and next year';

    /**
     * Execute the console command.
     */
    public function handle(OperationalCalendarService $calendarService): int
    {
        $yearArg = $this->argument('year');

        if ($yearArg !== null) {
            $year = (int) $yearArg;
            if ($year < 2020 || $year > 2050) {
                $this->error("Invalid year: {$yearArg}. Please provide a valid 4-digit year between 2020 and 2050.");

                return self::FAILURE;
            }

            $this->info("Seeding operational calendar for year {$year}...");
            $seededCount = $calendarService->generateForYear($year);
            $this->info("Successfully seeded {$seededCount} days for {$year}.");

            return self::SUCCESS;
        }

        $currentYear = Carbon::now()->year;
        $nextYear = $currentYear + 1;

        $this->info("Seeding operational calendar for current year ({$currentYear}) and next year ({$nextYear})...");

        $countCurrent = $calendarService->generateForYear($currentYear);
        $this->line(" - {$currentYear}: {$countCurrent} days seeded.");

        $countNext = $calendarService->generateForYear($nextYear);
        $this->line(" - {$nextYear}: {$countNext} days seeded.");

        $this->info("Successfully completed operational calendar seeding for {$currentYear} and {$nextYear}.");

        return self::SUCCESS;
    }
}
