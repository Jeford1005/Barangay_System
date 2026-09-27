<?php

use App\Http\Controllers\BlotterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:blotter.view'])->prefix('blotter')->name('blotter.')->group(function () {
    Route::get('/', [BlotterController::class, 'index'])->name('index');
    Route::get('/{blotter}/print', [BlotterController::class, 'print'])->whereNumber('blotter')->name('print');

    Route::middleware('permission:blotter.manage')->group(function () {
        Route::get('/create', [BlotterController::class, 'create'])->name('create');
        Route::post('/', [BlotterController::class, 'store'])->name('store');
        Route::get('/{blotter}/edit', [BlotterController::class, 'edit'])->whereNumber('blotter')->name('edit');
        Route::put('/{blotter}', [BlotterController::class, 'update'])->whereNumber('blotter')->name('update');
    });

    // Deleting a blotter entry is administrator-only.
    Route::delete('/{blotter}', [BlotterController::class, 'destroy'])
        ->middleware('admin')
        ->whereNumber('blotter')
        ->name('destroy');
});
