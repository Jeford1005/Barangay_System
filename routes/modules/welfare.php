<?php

use App\Http\Controllers\WelfareController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:welfare.view'])->prefix('welfare')->name('welfare.')->group(function () {
    Route::get('/', [WelfareController::class, 'index'])->name('index');

    // Staff and admins may both take in assistance requests.
    Route::middleware('permission:welfare.intake')->group(function () {
        Route::get('/create', [WelfareController::class, 'create'])->name('create');
        Route::post('/', [WelfareController::class, 'store'])->name('store');
    });

    // Reviewing (status changes), editing and deleting are administrator-only.
    Route::middleware('admin')->group(function () {
        Route::get('/{welfare}/edit', [WelfareController::class, 'edit'])->whereNumber('welfare')->name('edit');
        Route::put('/{welfare}', [WelfareController::class, 'update'])->whereNumber('welfare')->name('update');
        Route::delete('/{welfare}', [WelfareController::class, 'destroy'])->whereNumber('welfare')->name('destroy');
    });
});
