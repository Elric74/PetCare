<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LabReportController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Lab Reports ingestion endpoint (no CSRF, no auth; restrict later if needed)
Route::post('/lab-reports', [LabReportController::class, 'store']);
Route::get('/lab-reports/sync-status', [LabReportController::class, 'syncStatus']);

// Test route to verify cache is cleared
Route::get('/test-cache', function () {
    return response()->json(['message' => 'Cache is cleared!', 'timestamp' => now()]);
});
