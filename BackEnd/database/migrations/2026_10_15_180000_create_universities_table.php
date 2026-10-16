<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('universities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('specialty_id')->constrained('programs')->cascadeOnDelete();
            $table->string('name_fr');
            $table->string('name_en');
            $table->text('description_fr');
            $table->text('description_en');
            $table->string('location_fr');
            $table->string('location_en');
            $table->string('photo_path')->nullable();
            $table->decimal('minimum_score', 5, 2);
            $table->unsignedInteger('capacity');
            $table->unsignedInteger('admitted')->default(0);
            $table->decimal('cutoff_score', 5, 2)->nullable();
            $table->string('cutoff_code')->nullable();
            $table->timestamps();
            $table->index(['specialty_id', 'id']);
        });

        $cities = [
            ['Tunis', 'تونس'],
            ['Sfax', 'صفاقس'],
            ['Sousse', 'سوسة'],
            ['Monastir', 'المنستير'],
            ['Gabès', 'قابس'],
        ];

        $firstUniversityBySpecialty = [];
        $specialtyNamesEn = [
            'Médecine' => 'Medicine',
            'Informatique' => 'Computer Science',
            'Génie civil' => 'Civil Engineering',
            'Pharmacie' => 'Pharmacy',
            'Architecture' => 'Architecture',
            'Droit' => 'Law',
            'Économie' => 'Economics',
            'Lettres' => 'Literature',
        ];
        $now = now();

        foreach (DB::table('programs')->orderBy('id')->get() as $specialty) {
            foreach ($cities as $index => [$city]) {
                $universityId = DB::table('universities')->insertGetId([
                    'specialty_id' => $specialty->id,
                    'name_fr' => "Université de {$specialty->name} de {$city}",
                    'name_en' => "{$city} {$specialtyNamesEn[$specialty->name]} University",
                    'description_fr' => "Université de démonstration proposant la spécialité {$specialty->name}, avec des espaces d’apprentissage et des activités pratiques.",
                    'description_en' => "Demo university offering {$specialty->name}, with dedicated learning spaces and practical activities.",
                    'location_fr' => "{$city}, Tunisie",
                    'location_en' => "{$city}, Tunisia",
                    'photo_path' => null,
                    'minimum_score' => max(0, (float) $specialty->minimum_score + (($index - 2) * 0.25)),
                    'capacity' => intdiv($specialty->capacity, count($cities)) +
                        ($index < $specialty->capacity % count($cities) ? 1 : 0),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($index === 0) {
                    $firstUniversityBySpecialty[$specialty->id] = $universityId;
                }
            }
        }

        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('placed_university_id')
                ->nullable()
                ->after('placed_program_id')
                ->constrained('universities')
                ->nullOnDelete();
        });

        $applications = DB::table('applications')
            ->join('students', 'students.id', '=', 'applications.student_id')
            ->select('applications.*', 'students.code')
            ->get();

        foreach ($applications as $application) {
            $legacyChoices = json_decode($application->choices, true) ?: [];
            $choices = array_values(array_filter(array_map(
                fn($specialtyId) => $firstUniversityBySpecialty[(int) $specialtyId] ?? null,
                $legacyChoices
            )));
            $commitment = hash(
                'sha256',
                $application->salt . '|' . $application->code . '|' . implode(',', $choices)
            );

            DB::table('applications')->where('id', $application->id)->update([
                'choices' => json_encode($choices),
                'commitment' => $commitment,
                'placed_university_id' => $application->placed_program_id
                    ? ($firstUniversityBySpecialty[$application->placed_program_id] ?? null)
                    : null,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('placed_university_id');
        });

        Schema::dropIfExists('universities');
    }
};
