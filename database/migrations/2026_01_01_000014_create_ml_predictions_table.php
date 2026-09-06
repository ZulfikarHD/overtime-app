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
        Schema::create('ml_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ml_model_id')->constrained('ml_models')->onDelete('restrict');
            $table->string('target_type', 50);
            $table->unsignedBigInteger('target_id');
            $table->string('prediction_horizon', 30);
            $table->decimal('predicted_value', 10, 2);
            $table->decimal('confidence_interval_lower', 10, 2)->nullable();
            $table->decimal('confidence_interval_upper', 10, 2)->nullable();
            $table->decimal('risk_score', 5, 4)->nullable();
            $table->string('risk_level', 20)->nullable();
            $table->json('feature_impact_json')->nullable();
            $table->boolean('fallback_used')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['target_type', 'target_id', 'created_at'], 'idx_ml_pred_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ml_predictions');
    }
};
