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
        Schema::create('overtime_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('overtime_submission_id')->constrained('overtime_submissions')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees')->onDelete('restrict');
            $table->string('npk_snapshot', 20);
            $table->foreignId('capex_project_id')->nullable()->constrained('capex_projects')->onDelete('restrict');

            $table->decimal('hours_production', 4, 2)->default(0.00);
            $table->decimal('hours_tpm', 4, 2)->default(0.00);
            $table->decimal('hours_project', 4, 2)->default(0.00);
            $table->decimal('hours_others', 4, 2)->default(0.00);

            $table->decimal('total_hours', 4, 2)->storedAs('hours_production + hours_tpm + hours_project + hours_others');

            $table->decimal('hourly_rate_snapshot', 15, 2)->default(0.00);
            $table->decimal('total_cost_snapshot', 15, 2)->default(0.00);

            $table->enum('rca_category', [
                'MACHINE_BREAKDOWN',
                'SUPPLIER_DELAY',
                'QUALITY_REWORK',
                'CUSTOMER_RUSH',
                'TRIAL_MODEL',
                'FACILITY_MAINTENANCE',
                'OTHER',
            ])->nullable();
            $table->text('rca_notes')->nullable();
            $table->text('task_description')->nullable();

            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->integer('lock_version')->default(1);
            $table->timestamps();

            $table->index(['overtime_submission_id', 'employee_id'], 'idx_ot_items_sub_emp');
            $table->index(['employee_id', 'created_at'], 'idx_ot_items_emp_date');
            $table->index('status', 'idx_ot_items_status');
        });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'])) {
            DB::statement('CREATE INDEX idx_ot_items_capex ON overtime_items(capex_project_id) WHERE capex_project_id IS NOT NULL');
        } else {
            Schema::table('overtime_items', function (Blueprint $table) {
                $table->index('capex_project_id', 'idx_ot_items_capex');
            });
        }

        if (in_array($driver, ['pgsql', 'mysql'])) {
            DB::statement('ALTER TABLE overtime_items ADD CONSTRAINT chk_min_hours CHECK ((hours_production + hours_tpm + hours_project + hours_others) >= 0.50)');
            DB::statement('ALTER TABLE overtime_items ADD CONSTRAINT chk_capex_attribution CHECK ((hours_project = 0.00) OR (hours_project > 0.00 AND capex_project_id IS NOT NULL))');
            DB::statement('ALTER TABLE overtime_items ADD CONSTRAINT chk_hours_non_negative CHECK (hours_production >= 0.00 AND hours_tpm >= 0.00 AND hours_project >= 0.00 AND hours_others >= 0.00)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtime_items');
    }
};
