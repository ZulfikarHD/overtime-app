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
        Schema::create('overtime_item_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('overtime_item_id')->constrained('overtime_items')->onDelete('cascade');
            $table->string('action', 30);
            $table->foreignId('actor_user_id')->constrained('users')->onDelete('restrict');
            $table->json('previous_state')->nullable();
            $table->json('new_state');
            $table->text('notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('overtime_item_id', 'idx_item_audits_item');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtime_item_audits');
    }
};
