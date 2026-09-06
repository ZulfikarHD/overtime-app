<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ml_anomaly_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('overtime_item_id')->constrained('overtime_items')->onDelete('cascade');
            $table->foreignId('ml_model_id')->constrained('ml_models')->onDelete('restrict');
            $table->decimal('anomaly_score', 5, 4);
            $table->json('anomaly_reasons');
            $table->boolean('is_dismissed')->default(false);
            $table->foreignId('dismissed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dismissed_at')->nullable();
            $table->text('dismissal_note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'])) {
            DB::statement('CREATE INDEX idx_anomaly_item ON ml_anomaly_logs(overtime_item_id) WHERE is_dismissed = FALSE');
        } else {
            Schema::table('ml_anomaly_logs', function (Blueprint $table) {
                $table->index(['overtime_item_id', 'is_dismissed'], 'idx_anomaly_item');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ml_anomaly_logs');
    }
};
