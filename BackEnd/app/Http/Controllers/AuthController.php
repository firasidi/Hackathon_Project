<?php

namespace App\Http\Controllers;

use App\Models\ResetCode;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    // Only NON-sensitive fields. The CIN and the password are never returned.
    private function profileOf(Student $s): array
    {
        return [
            'code' => $s->code,
            'score' => $s->score,
            'name_fr' => $s->name_fr,
            'name_ar' => $s->name_ar,
            'institution_fr' => $s->institution_fr,
            'institution_ar' => $s->institution_ar,
            'email' => $s->email,
            'phone' => $s->phone,
            'city' => $s->city,
        ];
    }

    // CIN + password given by the ministry
    public function login(Request $r)
    {
        $d = $r->validate([
            'cin' => ['required', 'digits:8'],
            'password' => ['required', 'string'],
        ]);

        $key = 'login:' . $d['cin'] . '|' . $r->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'error' => 'too_many',
                'message' => 'Too many attempts. Try again in ' . RateLimiter::availableIn($key) . ' seconds.',
            ], 429);
        }

        $s = Student::where('cin', $d['cin'])->first();
        if (!$s || !Hash::check($d['password'], $s->password)) {
            RateLimiter::hit($key, 60);
            return response()->json(['error' => 'invalid_credentials', 'message' => 'Incorrect CIN or password.'], 401);
        }

        RateLimiter::clear($key);
        return [
            'token' => $s->createToken('insaf')->plainTextToken,
            'student' => ['code' => $s->code, 'score' => $s->score],
        ];
    }

    // "Forgot password?": send a 6-digit code by email
    public function forgot(Request $r)
    {
        $d = $r->validate(['cin' => ['required', 'digits:8']]);

        $key = 'forgot:' . $d['cin'] . '|' . $r->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json(['error' => 'too_many', 'message' => 'Too many requests. Try again later.'], 429);
        }
        RateLimiter::hit($key, 600);

        $s = Student::where('cin', $d['cin'])->first();
        if ($s && $s->email) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            ResetCode::where('student_id', $s->id)->delete();
            ResetCode::create([
                'student_id' => $s->id,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(10),
                'attempts' => 0,
            ]);

            try {
                Mail::raw(
                    "Insaf - votre code de vérification : {$code}\n\n" .
                    "Il expire dans 10 minutes. Si vous n'avez pas demandé ce code, ignorez ce message.",
                    fn($m) => $m->to($s->email)->subject('Insaf - Code de réinitialisation')
                );
            } catch (\Throwable $e) {
                report($e); // the real error is in storage/logs/laravel.log
            }
        }

        // Same answer whether the CIN exists or not, so nobody can test which CINs are valid
        return ['message' => 'If this CIN exists, a code was sent to the email registered with the ministry.'];
    }

    // Code + new password
    public function reset(Request $r)
    {
        $d = $r->validate([
            'cin' => ['required', 'digits:8'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $bad = response()->json(['error' => 'invalid_code', 'message' => 'Invalid or expired code.'], 422);

        $s = Student::where('cin', $d['cin'])->first();
        $rc = $s ? ResetCode::where('student_id', $s->id)->latest('id')->first() : null;

        if (!$rc || $rc->expires_at->isPast() || $rc->attempts >= 5) return $bad;
        if (!Hash::check($d['code'], $rc->code_hash)) {
            $rc->increment('attempts');
            return $bad;
        }

        $s->update(['password' => Hash::make($d['password'])]);
        ResetCode::where('student_id', $s->id)->delete();
        $s->tokens()->delete(); // log out every open session

        return ['message' => 'Password changed.'];
    }

    public function me(Request $r)
    {
        return $this->profileOf($r->user());
    }

    // The student's profile page: read
    public function profile(Request $r)
    {
        return $this->profileOf($r->user());
    }

    // The student's profile page: modify the contact details only
    public function updateProfile(Request $r)
    {
        $s = $r->user();

        $d = $r->validate([
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'regex:/^[0-9]{8}$/'],
            'city' => ['nullable', 'string', 'max:60'],
            'current_password' => ['nullable', 'string'],
        ]);

        // The email receives the reset codes, so changing it requires the current password
        if (strtolower($d['email']) !== strtolower((string) $s->email)) {
            $key = 'profile:' . $s->id;
            if (RateLimiter::tooManyAttempts($key, 5)) {
                return response()->json(['error' => 'too_many', 'message' => 'Too many attempts.'], 429);
            }
            if (!Hash::check($d['current_password'] ?? '', $s->password)) {
                RateLimiter::hit($key, 60);
                return response()->json(['error' => 'wrong_password', 'message' => 'Current password is incorrect.'], 422);
            }
            RateLimiter::clear($key);
        }

        // Name, institution, CIN and score are NOT editable here
        $s->update([
            'email' => $d['email'],
            'phone' => $d['phone'] ?? null,
            'city' => $d['city'] ?? null,
        ]);

        return $this->profileOf($s->fresh());
    }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()->delete();
        return ['ok' => true];
    }
}