<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// AuthController reads and updates these fields, but no earlier migration created them
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $t) {
            $t->string('name_fr')->nullable()->after('score');
            $t->string('name_ar')->nullable()->after('name_fr');
            $t->string('institution_fr')->nullable()->after('name_ar');
            $t->string('institution_ar')->nullable()->after('institution_fr');
            $t->string('phone', 8)->nullable()->after('email');
            $t->string('city', 60)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $t) {
            $t->dropColumn(['name_fr', 'name_ar', 'institution_fr', 'institution_ar', 'phone', 'city']);
        });
    }
};