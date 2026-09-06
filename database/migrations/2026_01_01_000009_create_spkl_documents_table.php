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
        Schema::create('spkl_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('overtime_submission_id')->unique()->constrained('overtime_submissions')->onDelete('cascade');
            $table->string('spkl_number', 100)->nullable();
            $table->string('file_path', 255)->nullable();
            $table->string('file_name', 255)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->enum('status', ['PENDING', 'ATTACHED', 'VERIFIED'])->default('PENDING');
            $table->date('due_date');
            $table->timestamp('attached_at')->nullable();
            $table->foreignId('attached_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'])) {
            DB::statement("CREATE INDEX idx_spkl_pending_due ON spkl_documents(status, due_date) WHERE status = 'PENDING'");
        } else {
            Schema::table('spkl_documents', function (Blueprint $table) {
                $table->index(['status', 'due_date'], 'idx_spkl_pending_due');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spkl_documents');
    }
};
