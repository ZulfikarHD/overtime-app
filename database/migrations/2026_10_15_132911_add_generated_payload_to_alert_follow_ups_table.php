<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep a snapshot of the system-generated 5W2H next to the user's final text and link legacy rows to a date.
     */
    public function up(): void
    {
        Schema::table('alert_follow_ups', function (Blueprint $table) {
            $table->json('generated_payload')->nullable()->after('how_much_hours');
            $table->timestamp('generated_at')->nullable()->after('generated_payload');
        });

        $this->backfillLegacyAlertDates();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alert_follow_ups', function (Blueprint $table) {
            $table->dropColumn(['generated_payload', 'generated_at']);
        });
    }

    /**
     * Legacy month-level rows get the date they were created on (stored in app timezone, Asia/Jakarta) as alert date.
     *
     * Done in PHP so it is the same on SQLite and MySQL/MariaDB (no vendor date functions).
     */
    protected function backfillLegacyAlertDates(): void
    {
        DB::table('alert_follow_ups')
            ->whereNull('alert_date')
            ->whereNotNull('created_at')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('alert_follow_ups')
                        ->where('id', $row->id)
                        ->update([
                            'alert_date' => Carbon::parse($row->created_at, 'Asia/Jakarta')->toDateString(),
                        ]);
                }
            });
    }
};
