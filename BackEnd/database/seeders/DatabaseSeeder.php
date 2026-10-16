<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Program;
use App\Models\Student;
use App\Models\University;
use App\Services\Insaf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(42); // same fake data every time

        $specialties = [
            ['name' => 'Médecine', 'capacity' => 15, 'minimum_score' => 16.50, 'description_fr' => 'Études médicales, sciences de la santé et pratique clinique.', 'description_en' => 'Medical studies, health sciences, and clinical practice.'],
            ['name' => 'Informatique', 'capacity' => 30, 'minimum_score' => 13.50, 'description_fr' => 'Informatique, systèmes logiciels et projets numériques.', 'description_en' => 'Computer science, software systems, and digital projects.'],
            ['name' => 'Génie civil', 'capacity' => 25, 'minimum_score' => 12.50, 'description_fr' => 'Conception des structures et projets de construction durable.', 'description_en' => 'Structural design and sustainable construction projects.'],
            ['name' => 'Pharmacie', 'capacity' => 15, 'minimum_score' => 15.00, 'description_fr' => 'Sciences pharmaceutiques et domaines de la santé.', 'description_en' => 'Pharmaceutical sciences and healthcare fields.'],
            ['name' => 'Architecture', 'capacity' => 15, 'minimum_score' => 13.00, 'description_fr' => 'Conception architecturale, maquettes et urbanisme.', 'description_en' => 'Architectural design, model-making, and urban planning.'],
            ['name' => 'Droit', 'capacity' => 30, 'minimum_score' => 11.00, 'description_fr' => 'Études juridiques, débats et pratique du droit.', 'description_en' => 'Legal studies, debate, and practical law.'],
            ['name' => 'Économie', 'capacity' => 25, 'minimum_score' => 10.00, 'description_fr' => 'Sciences économiques, gestion et analyse des marchés.', 'description_en' => 'Economics, management, and market analysis.'],
            ['name' => 'Lettres', 'capacity' => 25, 'minimum_score' => 9.00, 'description_fr' => 'Langues, littérature, communication et sciences humaines.', 'description_en' => 'Languages, literature, communication, and humanities.'],
        ];
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
        $cities = [
            ['Tunis', 'تونس'],
            ['Sfax', 'صفاقس'],
            ['Sousse', 'سوسة'],
            ['Monastir', 'المنستير'],
            ['Gabès', 'قابس'],
        ];

        foreach ($specialties as $specialtyData) {
            $specialty = Program::create($specialtyData);
            foreach ($cities as $index => [$city]) {
                University::create([
                    'specialty_id' => $specialty->id,
                    'name_fr' => "Université de {$specialtyData['name']} de {$city}",
                    'name_en' => "{$city} {$specialtyNamesEn[$specialtyData['name']]} University",
                    'description_fr' => "Université de démonstration proposant {$specialtyData['description_fr']}",
                    'description_en' => "Demo university offering {$specialtyData['description_en']}",
                    'location_fr' => "{$city}, Tunisie",
                    'location_en' => "{$city}, Tunisia",
                    'photo_path' => null,
                    'minimum_score' => max(0, $specialtyData['minimum_score'] + (($index - 2) * 0.25)),
                    'capacity' => intdiv($specialtyData['capacity'], count($cities)) +
                        ($index < $specialtyData['capacity'] % count($cities) ? 1 : 0),
                ]);
            }
        }

        $demoPassword = Hash::make('Insaf2026:password');

        // One bcrypt hash reused for all non-demo students.
        $pwd = Hash::make('Insaf2026');
        $universityIds = University::pluck('id')->all();

        $firstNames = ['Youssef', 'Mariam', 'Amine', 'Salma', 'Fares', 'Ines', 'Anis', 'Nour', 'Malek', 'Rania', 'Sami', 'Aya', 'Khalil', 'Lina', 'Mehdi', 'Sana', 'Tarek', 'Amina', 'Bilel', 'Hela'];
        $firstNamesAr = ['يوسف', 'مريم', 'أمين', 'سلمى', 'فارس', 'إيناس', 'أنيس', 'نور', 'مالك', 'رانيا', 'سامي', 'آية', 'خليل', 'لينا', 'مهدي', 'سناء', 'طارق', 'أمينة', 'بلال', 'هالة'];
        $familyNames = ['Trabelsi', 'Gharbi', 'Ben Youssef', 'Mansour', 'Jaziri', 'Khelifi', 'Saidi', 'Bouzid', 'Ayari', 'Haddad', 'Ben Amor', 'Mabrouk', 'Chaabane', 'Mejri', 'Kammoun', 'Dridi', 'Hamdi', 'Cherif', 'Brahmi', 'Nasri'];
        $familyNamesAr = ['الطرابلسي', 'الغربي', 'بن يوسف', 'منصور', 'الجزيري', 'الخليفي', 'السعيدي', 'بوزيد', 'العياري', 'الحداد', 'بن عمر', 'مبروك', 'شعبان', 'المجري', 'الكمون', 'الدريدي', 'الحمدي', 'الشريف', 'البراهمي', 'الناصري'];
        $institutions = [
            ['Lycée Pilote de Tunis', 'المعهد النموذجي بتونس'],
            ['Lycée Pilote de Sfax', 'المعهد النموذجي بصفاقس'],
            ['Lycée Pilote de Sousse', 'المعهد النموذجي بسوسة'],
            ['Lycée Pilote de Gabès', 'المعهد النموذجي بقابس'],
            ['Lycée Pilote de Bizerte', 'المعهد النموذجي ببنزرت'],
            ['Lycée secondaire de Kairouan', 'المعهد الثانوي بالقيروان'],
            ['Lycée secondaire de Monastir', 'المعهد الثانوي بالمنستير'],
            ['Lycée secondaire de Nabeul', 'المعهد الثانوي بنابل'],
        ];
        $cities = ['Tunis', 'Sfax', 'Sousse', 'Gabès', 'Bizerte', 'Kairouan', 'Monastir', 'Nabeul', 'Gafsa', 'Mahdia', 'Kef', 'Tozeur'];

        // S001 = the demo student, with a SYNTHETIC identity (change the names if you like).
        // Reset codes are sent to DEMO_EMAIL from your .env
        Student::create([
            'code' => 'S001',
            'score' => 18.90,
            'cin' => '12345678',
            'email' => env('DEMO_EMAIL', 'demo@insaf.test'),
            'password' => $demoPassword,
            'name_fr' => 'Ahmed Ben Salah',
            'name_ar' => 'أحمد بن صالح',
            'institution_fr' => 'Lycée Pilote de Gabès',
            'institution_ar' => 'المعهد النموذجي بقابس',
            'phone' => '20123456',
            'city' => 'Gabès',
        ]);

        for ($i = 2; $i <= 200; $i++) {
            $code = sprintf('S%03d', $i);
            $index = $i - 2;
            $firstNameIndex = $index % count($firstNames);
            $familyNameIndex = intdiv($index, count($firstNames)) % count($familyNames);
            $institution = $institutions[$index % count($institutions)];
            $isFiras = $i === 2;
            $s = Student::create([
                'code' => $code,
                'score' => mt_rand(800, 1900) / 100,
                'cin' => $isFiras ? '12345679' : (string) (10000000 + $i),
                'email' => $isFiras
                    ? env('DEMO_EMAIL', 'firas@insaf.test')
                    : strtolower($code) . '@insaf.test',
                'password' => $isFiras ? $demoPassword : $pwd,
                'name_fr' => ($isFiras ? 'Firas' : $firstNames[$firstNameIndex]) . ' ' . $familyNames[$familyNameIndex],
                'name_ar' => ($isFiras ? 'فراس' : $firstNamesAr[$firstNameIndex]) . ' ' . $familyNamesAr[$familyNameIndex],
                'institution_fr' => $institution[0],
                'institution_ar' => $institution[1],
                'phone' => (string) (21000000 + $index),
                'city' => $cities[$index % count($cities)],
            ]);

            $ids = $universityIds;
            shuffle($ids);
            $choices = array_slice($ids, 0, 5);
            $salt = bin2hex(random_bytes(16));

            Application::create([
                'student_id' => $s->id,
                'choices' => $choices,
                'salt' => $salt,
                'commitment' => Insaf::commit($code, $choices, $salt),
            ]);
        }
    }
}