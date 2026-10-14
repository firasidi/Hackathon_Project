<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('student_id')->index();
            $t->json('choices');                              // ranked list of program ids
            $t->string('salt');                               // random secret mixed into the hash
            $t->string('commitment');                         // the hash of the choices
            $t->unsignedBigInteger('placed_program_id')->nullable(); // result of the run
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};