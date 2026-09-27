<?php

use App\Http\Controllers\PurokController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:puroks.view'])->group(function () {
    Route::get('/puroks', [PurokController::class, 'index'])->name('puroks.index');

    // Writing puroks is administrator-only.
    Route::middleware('admin')->group(function () {
        Route::get('/puroks/create', [PurokController::class, 'create'])->name('puroks.create');
        Route::post('/puroks', [PurokController::class, 'store'])->name('puroks.store');
        Route::get('/puroks/{purok}/edit', [PurokController::class, 'edit'])->name('puroks.edit');
        Route::put('/puroks/{purok}', [PurokController::class, 'update'])->name('puroks.update');
        Route::delete('/puroks/{purok}', [PurokController::class, 'destroy'])->name('puroks.destroy');
    });
});
