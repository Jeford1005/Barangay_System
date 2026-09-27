<?php

use App\Http\Controllers\HouseholdController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:households.view'])->prefix('households')->name('households.')->group(function () {
    Route::get('/', [HouseholdController::class, 'index'])->name('index');
    Route::get('/{household}', [HouseholdController::class, 'show'])->whereNumber('household')->name('show');

    Route::middleware('permission:households.manage')->group(function () {
        Route::get('/create', [HouseholdController::class, 'create'])->name('create');
        Route::post('/', [HouseholdController::class, 'store'])->name('store');
        Route::get('/{household}/edit', [HouseholdController::class, 'edit'])->whereNumber('household')->name('edit');
        Route::put('/{household}', [HouseholdController::class, 'update'])->whereNumber('household')->name('update');
    });

    // Deleting a household is administrator-only (members are detached first).
    Route::delete('/{household}', [HouseholdController::class, 'destroy'])
        ->middleware('admin')
        ->whereNumber('household')
        ->name('destroy');
});
