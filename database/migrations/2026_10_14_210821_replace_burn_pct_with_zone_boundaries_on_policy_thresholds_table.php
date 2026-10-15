<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the warning/danger burn percentages with the 3-zone tolerance
     * boundaries (Hijau <= green_max_pct < Kuning <= yellow_max_pct < Merah).
     */
    public function up(): void
    {
        Schema::table('policy_thresholds', function (Blueprint $table) {
            $table->decimal('green_max_pct', 5, 2)->default(70.00)->after('spkl_grace_period_days');
            $table->decimal('yellow_max_pct', 5, 2)->default(90.00)->after('green_max_pct');
        });

        Schema::table('policy_thresholds', function (Blueprint $table) {
            $table->dropColumn(['burn_warning_pct', 'burn_danger_pct']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('policy_thresholds', function (Blueprint $table) {
            $table->decimal('burn_warning_pct', 5, 2)->default(100.00)->after('spkl_grace_period_days');
            $table->decimal('burn_danger_pct', 5, 2)->default(115.00)->after('burn_warning_pct');
        });

        Schema::table('policy_thresholds', function (Blueprint $table) {
            $table->dropColumn(['green_max_pct', 'yellow_max_pct']);
        });
    }
};
