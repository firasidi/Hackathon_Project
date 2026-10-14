<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commitments', function (Blueprint $t) {
            $t->id();
            $t->string('kind');                      // rules, merkle_root or results
            $t->string('hash');
            $t->string('hcs_tx_id');                 // Hedera transaction id
            $t->unsignedBigInteger('hcs_sequence');  // position in the Hedera topic
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commitments');
    }
};