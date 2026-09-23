<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ml_forecast_inputs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month'); // 1–12
            $table->unsignedTinyInteger('working_days');          // X1: Hari Kerja (planned)
            $table->unsignedInteger('production_volume');         // X2: Volume Produksi (planned)
            $table->unsignedSmallInteger('man_power');            // X3: Man Power (planned)
            $table->unsignedBigInteger('actual_overtime_index')->nullable(); // Y aktual (optional, filled when available)
            $table->timestamps();

            $table->unique(['year', 'month'], 'ml_forecast_unique_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ml_forecast_inputs');
    }
};
