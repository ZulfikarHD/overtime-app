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
        Schema::create('policy_thresholds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('cascade');
            $table->decimal('weekly_soft_limit_hours', 4, 1)->default(20.0);
            $table->integer('consecutive_weeks_alert')->default(3);
            $table->integer('spkl_grace_period_days')->default(2);
            $table->decimal('burn_warning_pct', 5, 2)->default(100.00);
            $table->decimal('burn_danger_pct', 5, 2)->default(115.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('policy_thresholds');
    }
};
