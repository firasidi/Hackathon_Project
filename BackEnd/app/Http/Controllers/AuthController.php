<?php

namespace App\Http\Controllers;

use App\Mail\ResetCodeMail;
use App\Mail\ProfileChangeCodeMail;
use App\Models\ProfileChangeCode;
use App\Models\ResetCode;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'is_admin' => $s->is_admin,
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
            $resetCode = ResetCode::create([
                'student_id' => $s->id,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(10),
                'attempts' => 0,
            ]);

            try {
                Mail::to($s->email)->send(new ResetCodeMail($code, $s->name_fr));
                $resetCode->update(['expires_at' => now()->addMinutes(10)]);
            } catch (\Throwable $e) {
                report($e);
                ResetCode::where('student_id', $s->id)->delete();

                return response()->json([
                    'error' => 'email_delivery_failed',
                    'message' => 'We could not send the reset code. Please try again later.',
                ], 503);
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
        ]);

        if (strtolower($d['email']) !== strtolower((string) $s->email) ||
            ($d['phone'] ?? null) !== $s->phone) {
            return response()->json([
                'error' => 'verification_required',
                'message' => 'Email and phone changes must be confirmed with the code sent to your current email address.',
            ], 422);
        }

        $s->update(['city' => $d['city'] ?? null]);

        return $this->profileOf($s->fresh());
    }

    public function requestProfileChange(Request $r)
    {
        $s = $r->user();
        if ($r->has('email')) {
            $r->merge(['email' => trim((string) $r->input('email'))]);
        }
        $d = $r->validate([
            'email' => ['sometimes', 'required', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'regex:/^[0-9]{8}$/'],
            'city' => ['sometimes', 'nullable', 'string', 'max:60'],
        ]);

        $pendingEmail = $d['email'] ?? $s->email;
        $pendingPhone = array_key_exists('phone', $d) ? $d['phone'] : $s->phone;
        $pendingCity = array_key_exists('city', $d) ? $d['city'] : $s->city;
        $emailChanged = strtolower((string) $pendingEmail) !== strtolower((string) $s->email);
        $phoneChanged = $pendingPhone !== $s->phone;

        if ($emailChanged && Student::where('email', $pendingEmail)->where('id', '!=', $s->id)->exists()) {
            return response()->json([
                'error' => 'email_unavailable',
                'message' => 'That email address is already associated with another account.',
            ], 409);
        }

        if (!$emailChanged && !$phoneChanged) {
            return response()->json([
                'error' => 'no_contact_change',
                'message' => 'No email or phone change was requested.',
            ], 422);
        }
        if (!filter_var($s->email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'error' => 'current_email_unavailable',
                'message' => 'A valid current email address is required to verify contact changes.',
            ], 422);
        }
        if (!app()->environment('testing') && in_array(config('mail.default'), ['log', 'array'], true)) {
            return response()->json([
                'error' => 'email_delivery_unconfigured',
                'message' => 'Email delivery is not configured. Your contact details were not changed.',
            ], 503);
        }

        $key = 'profile-change:' . $s->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json(['error' => 'too_many', 'message' => 'Too many requests. Try again later.'], 429);
        }
        RateLimiter::hit($key, 600);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        ProfileChangeCode::updateOrCreate(
            ['student_id' => $s->id],
            [
                'code_hash' => Hash::make($code),
                'original_email' => $s->email,
                'pending_email' => $emailChanged ? $pendingEmail : $s->email,
                'pending_phone' => $pendingPhone,
                'pending_city' => $pendingCity,
                'expires_at' => now()->addMinutes(10),
                'attempts' => 0,
            ]
        );

        try {
            Mail::to($s->email)->send(new ProfileChangeCodeMail($code));
        } catch (\Throwable $exception) {
            report($exception);
            ProfileChangeCode::where('student_id', $s->id)->delete();

            return response()->json([
                'error' => 'email_delivery_failed',
                'message' => 'We could not send a verification code. Your contact details were not changed.',
            ], 503);
        }

        $emailParts = explode('@', $s->email, 2);
        $localPart = $emailParts[0];
        $maskedEmail = substr($localPart, 0, 1) .
            str_repeat('*', max(2, min(6, strlen($localPart) - 1))) .
            '@' . $emailParts[1];

        return [
            'message' => 'A verification code was sent to your currently registered email address.',
            'emailHint' => $maskedEmail,
            'expiresInMinutes' => 10,
        ];
    }

    public function confirmProfileChange(Request $r)
    {
        $d = $r->validate(['code' => ['required', 'digits:6']]);
        $s = $r->user();

        $result = DB::transaction(function () use ($s, $d): array {
            $pending = ProfileChangeCode::where('student_id', $s->id)->lockForUpdate()->first();

            if (!$pending || $pending->expires_at->isPast() || $pending->attempts >= 5) {
                return ['error' => 'invalid_code', 'status' => 422];
            }
            if (!hash_equals((string) $s->email, (string) $pending->original_email)) {
                $pending->delete();
                return ['error' => 'request_expired', 'status' => 409];
            }
            if (!Hash::check($d['code'], $pending->code_hash)) {
                $pending->increment('attempts');
                return ['error' => 'invalid_code', 'status' => 422];
            }
            if (Student::where('email', $pending->pending_email)->where('id', '!=', $s->id)->exists()) {
                $pending->delete();
                return ['error' => 'email_unavailable', 'status' => 409];
            }

            $s->update([
                'email' => $pending->pending_email,
                'phone' => $pending->pending_phone,
                'city' => $pending->pending_city,
            ]);
            $pending->delete();

            return ['profile' => $this->profileOf($s->fresh())];
        });

        if (isset($result['error'])) {
            $messages = [
                'invalid_code' => 'Invalid or expired verification code.',
                'request_expired' => 'The registered email changed after this request. Request a new code.',
                'email_unavailable' => 'That email address is no longer available. Request a new code.',
            ];

            return response()->json([
                'error' => $result['error'],
                'message' => $messages[$result['error']],
            ], $result['status']);
        }

        RateLimiter::clear('profile-change:' . $s->id);
        return $result['profile'];
    }

    public function cancelProfileChange(Request $r)
    {
        ProfileChangeCode::where('student_id', $r->user()->id)->delete();
        RateLimiter::clear('profile-change:' . $r->user()->id);

        return ['ok' => true];
    }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()->delete();
        return ['ok' => true];
    }
}