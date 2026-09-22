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
        Schema::create('overtime_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('overtime_plan_id')
                ->constrained('overtime_plans')
                ->onDelete('cascade');
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->onDelete('restrict');
            $table->string('npk_snapshot', 20);
            $table->date('plan_date');
            $table->enum('day_type', ['HKN', 'HLR'])->default('HKN');

            // Category A = Production (kode 61,62)
            $table->decimal('hours_production', 5, 2)->default(0.00);
            // Category B = TPM (kode 65,66)
            $table->decimal('hours_tpm', 5, 2)->default(0.00);
            // Category C = Project/Kaizen (kode 67,68)
            $table->decimal('hours_project', 5, 2)->default(0.00);
            // Category D = Others (kode 63,64,69+)
            $table->decimal('hours_others', 5, 2)->default(0.00);

            $table->timestamps();

            $table->unique(
                ['overtime_plan_id', 'employee_id', 'plan_date'],
                'uk_plan_item_emp_date',
            );
            $table->index(['overtime_plan_id', 'plan_date'], 'idx_plan_items_plan_date');
            $table->index(['employee_id', 'plan_date'], 'idx_plan_items_emp_date');
        });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['pgsql', 'mysql', 'mariadb'])) {
            DB::statement(
                'ALTER TABLE overtime_plan_items ADD CONSTRAINT chk_plan_hours_non_negative '.
                'CHECK (hours_production >= 0.00 AND hours_tpm >= 0.00 AND hours_project >= 0.00 AND hours_others >= 0.00)',
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtime_plan_items');
    }
};
