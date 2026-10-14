<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Program;
use App\Models\Student;
use App\Services\Insaf;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(42); // same fake data every time

        $names = ['Médecine', 'Informatique', 'Génie civil', 'Pharmacie', 'Architecture', 'Droit', 'Économie', 'Lettres'];
        $caps  = [15, 30, 25, 15, 15, 30, 25, 25]; // 180 seats for 200 students

        foreach ($names as $i => $n) {
            Program::create(['name' => $n, 'capacity' => $caps[$i]]);
        }

        // S001 is our live demo student (no application yet)
        Student::create(['code' => 'S001', 'score' => 18.90]);

        for ($i = 2; $i <= 200; $i++) {
            $code = sprintf('S%03d', $i);
            $s = Student::create(['code' => $code, 'score' => mt_rand(800, 1900) / 100]);

            $ids = range(1, 8);
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