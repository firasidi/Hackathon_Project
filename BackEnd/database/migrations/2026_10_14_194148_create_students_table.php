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
            $t->string('code')->unique();      // pseudonym like S001, no real names
            $t->decimal('score', 5, 2);        // orientation score
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};