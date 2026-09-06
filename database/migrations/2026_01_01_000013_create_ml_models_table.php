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
        Schema::create('ml_models', function (Blueprint $table) {
            $table->id();
            $table->string('model_key', 50)->unique();
            $table->enum('model_type', [
                'DEMAND_FORECAST',
                'BURN_TRAJECTORY',
                'CAPEX_FORECAST',
                'ANOMALY_DETECTION',
            ]);
            $table->string('version', 20);
            $table->string('algorithm_name', 100);
            $table->json('hyperparameters')->default('{}');
            $table->json('metrics')->default('{}');
            $table->boolean('is_active')->default(false);
            $table->timestamp('trained_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ml_models');
    }
};
