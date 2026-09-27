<?php

use App\Http\Controllers\ResidentController;
use App\Http\Controllers\ResidentPhotoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:residents.view'])->prefix('residents')->name('residents.')->group(function () {
    Route::get('/', [ResidentController::class, 'index'])->name('index');
    Route::get('/directory', [ResidentController::class, 'directory'])->name('directory');
    Route::get('/{resident}', [ResidentController::class, 'show'])->whereNumber('resident')->name('show');

    Route::middleware('permission:residents.manage')->group(function () {
        Route::get('/create', [ResidentController::class, 'create'])->name('create');
        Route::post('/', [ResidentController::class, 'store'])->name('store');
        Route::get('/{resident}/edit', [ResidentController::class, 'edit'])->whereNumber('resident')->name('edit');
        Route::put('/{resident}', [ResidentController::class, 'update'])->whereNumber('resident')->name('update');
    });

    // Archive / restore is administrator-only.
    Route::middleware('admin')->group(function () {
        Route::post('/{resident}/archive', [ResidentController::class, 'archive'])->whereNumber('resident')->name('archive');
        Route::post('/{resident}/restore', [ResidentController::class, 'restore'])->whereNumber('resident')->name('restore');
    });
});

// Photo is streamed through an authenticated route (never public storage).
// It lives outside the permission group so a resident can fetch their OWN
// photo from the portal — ResidentPhotoController enforces the owner rule.
Route::get('residents/{resident}/photo', [ResidentPhotoController::class, 'show'])
    ->middleware('auth')
    ->whereNumber('resident')
    ->name('residents.photo');
