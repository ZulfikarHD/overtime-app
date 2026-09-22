<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spl_entries', function (Blueprint $table) {
            $table->string('section_name_snapshot')->nullable()->after('department_id');
            $table->string('department_name_snapshot')->nullable()->after('section_name_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('spl_entries', function (Blueprint $table) {
            $table->dropColumn(['section_name_snapshot', 'department_name_snapshot']);
        });
    }
};
