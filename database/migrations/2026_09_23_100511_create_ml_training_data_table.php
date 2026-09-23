<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ml_training_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month'); // 1–12
            $table->unsignedTinyInteger('working_days');   // X1: Jumlah Hari Kerja
            $table->unsignedInteger('production_volume');  // X2: Volume Produksi (unit/bulan)
            $table->unsignedSmallInteger('man_power');     // X3: Total Man Power
            $table->unsignedBigInteger('overtime_index'); // Y:  Total Index Overtime
            $table->timestamps();

            $table->unique(['year', 'month'], 'ml_training_unique_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ml_training_data');
    }
};
