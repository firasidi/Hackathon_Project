<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();       // pseudonym like S001
            $t->decimal('score', 5, 2);         // orientation score
            $t->string('cin', 8)->nullable()->unique(); // username given by the ministry
            $t->string('email')->nullable();    // where the reset code is sent
            $t->string('password')->nullable(); // stored hashed, never in plain text
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};