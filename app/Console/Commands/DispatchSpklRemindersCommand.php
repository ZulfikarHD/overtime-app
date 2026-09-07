<?php

namespace App\Console\Commands;

use App\Jobs\SendSpklReminderJob;
use Illuminate\Console\Command;

class DispatchSpklRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'overtime:spkl-reminders {--sync : Run the reminder job synchronously}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check pending and overdue SPKL documents and dispatch reminder notifications';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking pending SPKL documents for reminders...');

        if ($this->option('sync')) {
            SendSpklReminderJob::dispatchSync();
            $this->info('SPKL reminder job completed synchronously.');
        } else {
            SendSpklReminderJob::dispatch();
            $this->info('SPKL reminder job dispatched to queue.');
        }

        return self::SUCCESS;
    }
}
