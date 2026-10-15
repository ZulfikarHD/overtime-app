<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link follow-ups to a concrete alert (section + date). Legacy month-level rows keep a NULL alert_date.
     */
    public function up(): void
    {
        Schema::table('alert_follow_ups', function (Blueprint $table) {
            $table->date('alert_date')->nullable()->after('section_id');
            $table->unsignedSmallInteger('streak_days')->default(0)->after('index_pct');

            $table->index(['section_id', 'alert_date'], 'idx_alert_follow_ups_section_alert_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alert_follow_ups', function (Blueprint $table) {
            $table->dropIndex('idx_alert_follow_ups_section_alert_date');
            $table->dropColumn(['alert_date', 'streak_days']);
        });
    }
};
