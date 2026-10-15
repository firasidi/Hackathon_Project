<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InsafController;
use Illuminate\Support\Facades\Route;

// ── Public: published information and login ──
Route::get('/programs', [InsafController::class, 'programs']);
Route::get('/commitments', [InsafController::class, 'commitments']);

Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgot']);
Route::post('/reset-password', [AuthController::class, 'reset']);

// ── Protected: a valid login token is required ──
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/students/{code}', [InsafController::class, 'student']);
    Route::post('/apply', [InsafController::class, 'apply']);
    Route::post('/verify', [InsafController::class, 'verify']);

    // Demo-only buttons (ministry run and hacker); they also need DEMO_MODE=true
    Route::post('/admin/run', [InsafController::class, 'run']);
    Route::post('/admin/tamper/{code}', [InsafController::class, 'tamper']);
});