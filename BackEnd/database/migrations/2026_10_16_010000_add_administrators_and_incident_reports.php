<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
        });

        Schema::create('incident_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('incident_id')->unique();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('submission_reference');
            $table->string('original_hash', 64);
            $table->string('current_hash', 64);
            $table->string('hedera_topic_id')->nullable();
            $table->unsignedBigInteger('hedera_sequence')->nullable();
            $table->string('hedera_consensus_timestamp')->nullable();
            $table->string('status')->default('Pending Review');
            $table->json('report_data');
            $table->string('pdf_path');
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_reports');

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
