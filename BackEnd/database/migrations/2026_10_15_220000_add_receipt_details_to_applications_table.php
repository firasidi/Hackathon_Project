<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->json('receipt_choices')->nullable();
            $table->string('receipt_salt')->nullable();
            $table->string('receipt_hash')->nullable();
            $table->string('receipt_transaction_id')->nullable();
            $table->unsignedBigInteger('receipt_sequence')->nullable();
            $table->timestamp('receipt_submitted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn([
                'receipt_choices',
                'receipt_salt',
                'receipt_hash',
                'receipt_transaction_id',
                'receipt_sequence',
                'receipt_submitted_at',
            ]);
        });
    }
};
