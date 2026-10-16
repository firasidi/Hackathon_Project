<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commitments', function (Blueprint $table) {
            $table->string('hedera_network')->nullable();
            $table->string('hedera_topic_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('commitments', function (Blueprint $table) {
            $table->dropColumn(['hedera_network', 'hedera_topic_id']);
        });
    }
};
