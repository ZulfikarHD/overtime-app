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
        Schema::create('alert_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('restrict');
            $table->foreignId('section_id')->nullable()->constrained('sections')->onDelete('restrict');
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedTinyInteger('fiscal_month');
            $table->string('zone', 10);
            $table->decimal('index_pct', 8, 2)->default(0);

            // 5W2H
            $table->text('what_text')->nullable();
            $table->text('why_text')->nullable();
            $table->date('when_date')->nullable();
            $table->string('where_location')->nullable();
            $table->foreignId('who_department_id')->nullable()->constrained('departments')->onDelete('restrict');
            $table->text('how_text')->nullable();
            $table->unsignedBigInteger('how_much_cost_idr')->default(0);
            $table->decimal('how_much_hours', 8, 2)->default(0);

            $table->string('status', 10)->default('draft');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('sent_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'section_id', 'fiscal_year', 'fiscal_month'], 'idx_alert_follow_ups_scope_period');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_follow_ups');
    }
};
