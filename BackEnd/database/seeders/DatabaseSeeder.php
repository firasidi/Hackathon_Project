<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Program;
use App\Models\Student;
use App\Services\Insaf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(42); // same fake data every time

        $names = ['Médecine', 'Informatique', 'Génie civil', 'Pharmacie', 'Architecture', 'Droit', 'Économie', 'Lettres'];
        $caps  = [15, 30, 25, 15, 15, 30, 25, 25];

        foreach ($names as $i => $n) {
            Program::create(['name' => $n, 'capacity' => $caps[$i]]);
        }

        // One bcrypt hash reused for all fake students (hashing 200 times would be slow)
        $pwd = Hash::make('Insaf2026');

        // S001 = the demo student. Reset codes are sent to DEMO_EMAIL from your .env
        Student::create([
            'code' => 'S001',
            'score' => 18.90,
            'cin' => '12345678',
            'email' => env('DEMO_EMAIL', 'demo@insaf.test'),
            'password' => $pwd,
        ]);

        for ($i = 2; $i <= 200; $i++) {
            $code = sprintf('S%03d', $i);
            $s = Student::create([
                'code' => $code,
                'score' => mt_rand(800, 1900) / 100,
                'cin' => (string) (10000000 + $i),
                'email' => null,
                'password' => $pwd,
            ]);

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