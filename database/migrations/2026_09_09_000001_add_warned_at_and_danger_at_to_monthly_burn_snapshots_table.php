<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('monthly_burn_snapshots', function (Blueprint $table) {
            $table->timestamp('warned_at')->nullable()->after('burn_zone');
            $table->timestamp('danger_at')->nullable()->after('warned_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_burn_snapshots', function (Blueprint $table) {
            $table->dropColumn(['warned_at', 'danger_at']);
        });
    }
};
