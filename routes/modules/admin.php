<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\ArchiveController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\OfficialController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Administrator screens
|--------------------------------------------------------------------------
|
| Accounts, officials, the audit trail, CSV exports and the archive.
| (Account routes are registered here too — the whole admin surface in one
| place.)
|
*/

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {

    /* --------------------------------- accounts -------------------------------- */
    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::post('/accounts/{user}/approve', [AccountController::class, 'approve'])->name('accounts.approve');
    Route::post('/accounts/{user}/reject', [AccountController::class, 'reject'])->name('accounts.reject');
    Route::post('/accounts/{user}/resident-profile-from-application', [AccountController::class, 'createResidentFromApplication'])->name('accounts.profile-from-application');
    Route::post('/accounts/{user}/suspend', [AccountController::class, 'suspend'])->name('accounts.suspend');
    Route::post('/accounts/{user}/reactivate', [AccountController::class, 'reactivate'])->name('accounts.reactivate');
    Route::patch('/accounts/{user}/role', [AccountController::class, 'changeRole'])->name('accounts.role');

    /* --------------------------------- officials ------------------------------- */
    Route::get('/officials', [OfficialController::class, 'index'])->name('officials.index');
    Route::post('/officials', [OfficialController::class, 'store'])->name('officials.store');
    Route::put('/officials/{official}', [OfficialController::class, 'update'])->whereNumber('official')->name('officials.update');
    Route::delete('/officials/{official}', [OfficialController::class, 'destroy'])->whereNumber('official')->name('officials.destroy');

    /* -------------------------------- audit trail ------------------------------ */
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    /* ---------------------------------- exports -------------------------------- */
    Route::get('/exports/{dataset}', [ExportController::class, '__invoke'])
        ->whereIn('dataset', ['residents', 'households', 'blotter', 'welfare', 'certificates'])
        ->name('exports');
});

/* ---------------------------------- archive --------------------------------- */
Route::middleware(['auth', 'admin'])->prefix('archive')->name('archive.')->group(function () {
    Route::get('/', [ArchiveController::class, 'index'])->name('index');
    Route::post('/residents/{resident}/restore', [ArchiveController::class, 'restore'])->whereNumber('resident')->name('residents.restore');
    Route::delete('/residents/{resident}', [ArchiveController::class, 'destroy'])->whereNumber('resident')->name('residents.destroy');
});
