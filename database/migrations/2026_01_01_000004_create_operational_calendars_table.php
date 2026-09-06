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
        Schema::create('operational_calendars', function (Blueprint $table) {
            $table->date('calendar_date')->primary();
            $table->enum('day_type', ['HKN', 'HLR'])->default('HKN');
            $table->boolean('is_holiday')->default(false);
            $table->string('holiday_name', 100)->nullable();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operational_calendars');
    }
};
