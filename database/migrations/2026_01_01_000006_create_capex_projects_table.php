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
        Schema::create('capex_projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_code', 50)->unique();
            $table->string('asset_code', 50)->nullable();
            $table->string('name', 200);
            $table->foreignId('department_id')->constrained('departments')->onDelete('restrict');
            $table->decimal('allocated_labor_hours', 8, 2)->default(0.00);
            $table->decimal('allocated_labor_budget_idr', 15, 2)->default(0.00);
            $table->decimal('physical_progress_pct', 5, 2)->default(0.00);
            $table->enum('status', ['PLANNING', 'ACTIVE', 'ON_HOLD', 'COMPLETED', 'CLOSED'])->default('ACTIVE');
            $table->date('start_date');
            $table->date('target_end_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('capex_projects');
    }
};
