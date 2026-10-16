<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InsafController;
use App\Http\Controllers\IncidentReportController;
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
    Route::get('/dashboard', [InsafController::class, 'dashboard']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/application', [InsafController::class, 'application']);
    Route::get('/receipt', [InsafController::class, 'receipt']);
    Route::get('/students/{code}', [InsafController::class, 'student']);
    Route::post('/apply', [InsafController::class, 'apply']);
    Route::post('/verify', [InsafController::class, 'verify']);
    Route::post('/incident-reports', [IncidentReportController::class, 'store']);

    Route::middleware('administrator')->prefix('admin')->group(function () {
        // These demo actions are additionally restricted by the controller to local/testing.
        Route::post('/run', [InsafController::class, 'run']);
        Route::post('/tamper/{code}', [InsafController::class, 'tamper']);
        Route::get('/incident-reports', [IncidentReportController::class, 'index']);
        Route::get('/incident-reports/{incidentId}', [IncidentReportController::class, 'show']);
        Route::get('/incident-reports/{incidentId}/pdf', [IncidentReportController::class, 'download']);
        Route::patch('/incident-reports/{incidentId}', [IncidentReportController::class, 'updateStatus']);
    });
});