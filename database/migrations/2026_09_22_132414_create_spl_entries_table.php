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
        Schema::create('spl_entries', function (Blueprint $table) {
            $table->id();

            // Employee snapshot (employee_id nullable: may not exist in master data)
            $table->foreignId('employee_id')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();
            $table->string('npk_snapshot', 20);
            $table->string('employee_name_snapshot', 150);

            // Optional section/department if resolvable from employee
            $table->foreignId('section_id')
                ->nullable()
                ->constrained('sections')
                ->nullOnDelete();
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete();

            // Execution date (constructed from sheet number + import month/year)
            $table->date('realization_date');
            $table->enum('day_type', ['HKN', 'HLR'])->default('HKN');

            // Work time window from SPL
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('total_hours', 5, 2); // TOTAL (JAM)

            // Job classification
            $table->text('jenis_pekerjaan')->nullable();            // JENIS PEKERJAAN
            $table->unsignedSmallInteger('type_ot_code')->nullable(); // TYPE OT (e.g. 61, 67)
            $table->text('keterangan_lembur')->nullable();           // KETERANGAN LEMBUR
            $table->text('description')->nullable();                 // Description column
            $table->string('action', 200)->nullable();               // Action column

            // Audit trail
            $table->foreignId('imported_by_user_id')
                ->constrained('users')
                ->onDelete('restrict');

            $table->timestamps();

            // Upsert key: one SPL entry per person per date per start time
            $table->unique(
                ['npk_snapshot', 'realization_date', 'start_time'],
                'uk_spl_npk_date_start',
            );

            $table->index('realization_date', 'idx_spl_date');
            $table->index(['section_id', 'realization_date'], 'idx_spl_section_date');
            $table->index('imported_by_user_id', 'idx_spl_imported_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spl_entries');
    }
};
