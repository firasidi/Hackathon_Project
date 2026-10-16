<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incident_reports', function (Blueprint $table) {
            $table->string('report_type')->default('integrity')->after('student_id');
            $table->string('submission_reference')->nullable()->change();
            $table->string('original_hash', 64)->nullable()->change();
            $table->string('current_hash', 64)->nullable()->change();
            $table->string('pdf_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('incident_reports', function (Blueprint $table) {
            $table->dropColumn('report_type');
            $table->string('submission_reference')->nullable(false)->change();
            $table->string('original_hash', 64)->nullable(false)->change();
            $table->string('current_hash', 64)->nullable(false)->change();
            $table->string('pdf_path')->nullable(false)->change();
        });
    }
};
