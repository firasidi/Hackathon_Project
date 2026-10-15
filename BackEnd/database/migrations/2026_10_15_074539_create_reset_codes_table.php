<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reset_codes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('student_id')->index();
            $t->string('code_hash');                  // the 6-digit code is stored hashed
            $t->timestamp('expires_at');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reset_codes');
    }
};