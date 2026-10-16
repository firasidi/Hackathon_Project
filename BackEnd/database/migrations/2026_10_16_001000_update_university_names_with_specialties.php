<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $specialtyNames = [
        'Médecine' => ['fr' => 'médecine', 'en' => 'Medicine'],
        'Informatique' => ['fr' => 'informatique', 'en' => 'Computer Science'],
        'Génie civil' => ['fr' => 'génie civil', 'en' => 'Civil Engineering'],
        'Pharmacie' => ['fr' => 'pharmacie', 'en' => 'Pharmacy'],
        'Architecture' => ['fr' => 'architecture', 'en' => 'Architecture'],
        'Droit' => ['fr' => 'droit', 'en' => 'Law'],
        'Économie' => ['fr' => 'économie', 'en' => 'Economics'],
        'Lettres' => ['fr' => 'lettres', 'en' => 'Literature'],
    ];

    public function up(): void
    {
        $universities = DB::table('universities')
            ->join('programs', 'programs.id', '=', 'universities.specialty_id')
            ->select(
                'universities.id',
                'universities.location_fr',
                'universities.location_en',
                'programs.name as specialty_name'
            )
            ->get();

        foreach ($universities as $university) {
            $specialty = $this->specialtyNames[$university->specialty_name] ?? null;
            if ($specialty === null) {
                continue;
            }

            $cityFr = explode(',', $university->location_fr)[0];
            $cityEn = explode(',', $university->location_en)[0];
            DB::table('universities')->where('id', $university->id)->update([
                'name_fr' => "Université de {$specialty['fr']} de {$cityFr}",
                'name_en' => "{$cityEn} {$specialty['en']} University",
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('universities')->get() as $university) {
            $cityFr = explode(',', $university->location_fr)[0];
            $cityEn = explode(',', $university->location_en)[0];
            DB::table('universities')->where('id', $university->id)->update([
                'name_fr' => "Université démo de {$cityFr}",
                'name_en' => "{$cityEn} Demo University",
                'updated_at' => now(),
            ]);
        }
    }
};
