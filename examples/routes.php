<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExampleController;

/**
 * Example routes for testing the Excel Export Streamer plugin
 * Add these to your Laravel app's routes/web.php file
 */

// Basic exports
Route::get('/export/users/csv', [ExampleController::class, 'exportUsersCsv'])
    ->name('export.users.csv');

Route::get('/export/users/excel', [ExampleController::class, 'exportUsersExcel'])
    ->name('export.users.excel');

Route::get('/export/custom-data', [ExampleController::class, 'exportCustomData'])
    ->name('export.custom');

// Advanced exports with filters
Route::get('/export/users/filtered', [ExampleController::class, 'exportFilteredUsers'])
    ->name('export.users.filtered');

Route::get('/export/users/large', [ExampleController::class, 'exportLargeDataset'])
    ->name('export.users.large');

// Example with middleware protection
Route::middleware(['auth'])->group(function () {
    Route::get('/admin/export/users', [ExampleController::class, 'exportUsersCsv'])
        ->name('admin.export.users');
});

// API routes (add to routes/api.php)
Route::prefix('api/v1')->group(function () {
    Route::get('/export/users', [ExampleController::class, 'exportUsersCsv']);
    Route::post('/export/users/filtered', [ExampleController::class, 'exportFilteredUsers']);
});