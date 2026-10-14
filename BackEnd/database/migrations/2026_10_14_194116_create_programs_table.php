<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $t) {
            $t->id();
            $t->string('name');                                   // e.g. Médecine
            $t->unsignedInteger('capacity');                      // number of seats
            $t->unsignedInteger('admitted')->default(0);          // seats filled after the run
            $t->decimal('cutoff_score', 5, 2)->nullable();        // score of the last admitted student
            $t->string('cutoff_code')->nullable();                // code of the last admitted student
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};