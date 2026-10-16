<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('receipt_network')->nullable();
            $table->string('receipt_topic_id')->nullable();
            $table->string('receipt_consensus_timestamp')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn([
                'receipt_network',
                'receipt_topic_id',
                'receipt_consensus_timestamp',
            ]);
        });
    }
};
