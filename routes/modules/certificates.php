<?php

use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CertificateRequestController;
use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

/* ---------------------------------------------------------------- */
/* Office: issue, list, print and void certificates                  */
/* ---------------------------------------------------------------- */
Route::middleware(['auth', 'permission:certificates.view'])->prefix('certificates')->name('certificates.')->group(function () {
    Route::get('/', [CertificateController::class, 'index'])->name('index');
    Route::get('/{issuance}/print', [CertificateController::class, 'print'])->whereNumber('issuance')->name('print');

    Route::middleware('permission:certificates.issue')->group(function () {
        Route::get('/create', [CertificateController::class, 'create'])->name('create');
        Route::post('/', [CertificateController::class, 'store'])->name('store');
    });

    // Voiding is administrator-only.
    Route::post('/{issuance}/void', [CertificateController::class, 'void'])
        ->middleware('admin')
        ->whereNumber('issuance')
        ->name('void');
});

/* ---------------------------------------------------------------- */
/* Admin: certificate type catalog (CLR / COR / IND …)               */
/* ---------------------------------------------------------------- */
Route::middleware(['auth', 'admin'])->prefix('admin/certificate-types')->name('admin.certificate-types.')->group(function () {
    Route::get('/', [DocumentController::class, 'index'])->name('index');
    Route::post('/', [DocumentController::class, 'store'])->name('store');
    Route::put('/{document}', [DocumentController::class, 'update'])->whereNumber('document')->name('update');
    Route::delete('/{document}', [DocumentController::class, 'destroy'])->whereNumber('document')->name('destroy');
});

/* ---------------------------------------------------------------- */
/* Certificate request queue (residents ask online, office reviews)   */
/* ---------------------------------------------------------------- */
Route::middleware(['auth', 'permission:certificate-requests.view'])->group(function () {
    Route::get('/admin/certificate-requests', [CertificateRequestController::class, 'index'])
        ->name('admin.certificate-requests.index');

    Route::middleware('admin')->group(function () {
        Route::post('/admin/certificate-requests/{request}/approve', [CertificateRequestController::class, 'approve'])
            ->whereNumber('request')
            ->name('admin.certificate-requests.approve');
        Route::post('/admin/certificate-requests/{request}/reject', [CertificateRequestController::class, 'reject'])
            ->whereNumber('request')
            ->name('admin.certificate-requests.reject');
    });
});
