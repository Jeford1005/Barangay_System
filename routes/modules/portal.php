<?php

use App\Http\Controllers\ResidentPortalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Resident self-service portal (/my)
|--------------------------------------------------------------------------
|
| Residents cannot reach any office module; this is their whole surface.
|
*/

Route::middleware(['auth', 'resident'])->prefix('my')->name('resident.')->group(function () {
    Route::get('/', [ResidentPortalController::class, 'index'])->name('portal');
    Route::get('/photo', [ResidentPortalController::class, 'photo'])->name('photo');
    Route::put('/contact', [ResidentPortalController::class, 'updateContact'])->name('contact.update');

    Route::get('/requests', [ResidentPortalController::class, 'requests'])->name('requests');
    Route::post('/requests', [ResidentPortalController::class, 'storeRequest'])
        ->middleware('throttle:10,1')
        ->name('requests.store');
    Route::post('/requests/{request}/cancel', [ResidentPortalController::class, 'cancelRequest'])
        ->middleware('throttle:15,1')
        ->whereNumber('request')
        ->name('requests.cancel');
    Route::get('/requests/{request}/certificate', [ResidentPortalController::class, 'downloadCertificate'])
        ->whereNumber('request')
        ->name('requests.certificate');
});
