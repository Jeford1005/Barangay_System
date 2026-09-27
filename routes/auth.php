<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetCodeController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
| Only the flows that are actually implemented: login and logout.
|
| Public registration is resident-only and creates a pending account.
| Administrator and staff accounts are provisioned by trusted administrators
| or controlled seed/operations flows; the registration form cannot select
| an office role.
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    // The per-account limiter inside LoginRequest keys on email + IP, so
    // rotating the source address would otherwise allow unlimited guesses
    // against any number of addresses from one host. This is the IP ceiling.
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:20,1');

    // Resident self-registration (accounts start pending admin approval)
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:5,1');

    // Password reset via emailed verification code (Gmail SMTP / log fallback)
    Route::get('forgot-password', [PasswordResetCodeController::class, 'request'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetCodeController::class, 'email'])
        ->middleware('throttle:5,1')
        ->name('password.email');

    Route::get('reset-password', [PasswordResetCodeController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [PasswordResetCodeController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
