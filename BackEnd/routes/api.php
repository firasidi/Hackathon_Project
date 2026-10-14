<?php

use App\Http\Controllers\InsafController;
use Illuminate\Support\Facades\Route;

Route::get('/programs', [InsafController::class, 'programs']);
Route::get('/commitments', [InsafController::class, 'commitments']);
Route::get('/students/{code}', [InsafController::class, 'student']);
Route::post('/apply', [InsafController::class, 'apply']);
Route::post('/verify', [InsafController::class, 'verify']);
Route::post('/admin/run', [InsafController::class, 'run']);
Route::post('/admin/tamper/{code}', [InsafController::class, 'tamper']);