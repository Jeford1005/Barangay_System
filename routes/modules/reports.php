<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:reports.view'])->prefix('reports')->name('reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('/population', [ReportController::class, 'population'])->name('population');
    Route::get('/blotter', [ReportController::class, 'blotter'])->name('blotter');
    Route::get('/welfare', [ReportController::class, 'welfare'])->name('welfare');
});

Route::get('/analytics', [AnalyticsController::class, '__invoke'])
    ->middleware(['auth', 'permission:analytics.view'])
    ->name('analytics');
