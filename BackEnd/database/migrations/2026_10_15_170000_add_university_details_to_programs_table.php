<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->string('university_name_fr')->nullable();
            $table->string('university_name_en')->nullable();
            $table->text('description_fr')->nullable();
            $table->text('description_en')->nullable();
            $table->string('location_fr')->nullable();
            $table->string('location_en')->nullable();
            $table->decimal('minimum_score', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn([
                'university_name_fr',
                'university_name_en',
                'description_fr',
                'description_en',
                'location_fr',
                'location_en',
                'minimum_score',
            ]);
        });
    }
};
