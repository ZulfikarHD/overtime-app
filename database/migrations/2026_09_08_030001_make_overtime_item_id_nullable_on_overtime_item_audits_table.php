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
        Schema::table('overtime_item_audits', function (Blueprint $table) {
            $table->foreignId('overtime_item_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('overtime_item_audits', function (Blueprint $table) {
            $table->foreignId('overtime_item_id')->nullable(false)->change();
        });
    }
};
