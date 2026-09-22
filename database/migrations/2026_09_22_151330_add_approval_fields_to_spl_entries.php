<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The approval columns (status, reviewed_by_user_id, etc.) may already exist
        // if a prior partial run added them before an index conflict aborted the migration.
        // We add only what is genuinely missing.
        Schema::table('spl_entries', function (Blueprint $table): void {
            $existing = array_column(Schema::getColumns('spl_entries'), 'name');

            if (! in_array('status', $existing, true)) {
                $table->string('status')->default('PENDING')->after('imported_by_user_id');
            }
            if (! in_array('reviewed_by_user_id', $existing, true)) {
                $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('status');
            }
            if (! in_array('reviewed_at', $existing, true)) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_user_id');
            }
            if (! in_array('rejection_reason', $existing, true)) {
                $table->text('rejection_reason')->nullable()->after('reviewed_at');
            }
            if (! in_array('lock_version', $existing, true)) {
                $table->unsignedInteger('lock_version')->default(0)->after('rejection_reason');
            }

            $indexes = array_column(Schema::getIndexes('spl_entries'), 'name');
            if (! in_array('idx_spl_status', $indexes, true)) {
                $table->index('status', 'idx_spl_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('spl_entries', function (Blueprint $table): void {
            $existing = array_column(Schema::getColumns('spl_entries'), 'name');
            $indexes = array_column(Schema::getIndexes('spl_entries'), 'name');

            if (in_array('idx_spl_status', $indexes, true)) {
                $table->dropIndex('idx_spl_status');
            }

            $toDrop = array_intersect(['status', 'reviewed_by_user_id', 'reviewed_at', 'rejection_reason', 'lock_version'], $existing);
            if ($toDrop) {
                $table->dropColumn(array_values($toDrop));
            }
        });
    }
};
