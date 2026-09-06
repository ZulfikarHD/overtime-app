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
        Schema::create('overtime_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('submission_code', 50)->unique();
            $table->date('submission_date');
            $table->date('operational_date');
            $table->foreign('operational_date')->references('calendar_date')->on('operational_calendars')->onDelete('restrict');
            $table->enum('day_type', ['HKN', 'HLR']);
            $table->foreignId('department_id')->constrained('departments')->onDelete('restrict');
            $table->foreignId('section_id')->constrained('sections')->onDelete('restrict');
            $table->foreignId('submitted_by_user_id')->constrained('users')->onDelete('restrict');
            $table->enum('status', ['DRAFT', 'SUBMITTED', 'PARTIALLY_APPROVED', 'APPROVED', 'REJECTED'])->default('SUBMITTED');
            $table->decimal('total_hours_cached', 8, 2)->default(0.00);
            $table->text('submission_notes')->nullable();
            $table->timestamps();

            $table->index(['operational_date', 'section_id', 'status'], 'idx_ot_sub_date_sec');
            $table->index(['status', 'department_id'], 'idx_ot_sub_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtime_submissions');
    }
};
