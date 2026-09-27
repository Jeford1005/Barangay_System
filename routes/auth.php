<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:20,1');

    // Resident self-registration (dialog on the sign-in page).
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('register');

    // Password reset by email
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

/*
|--------------------------------------------------------------------------
| Administration
|--------------------------------------------------------------------------
|
| Administrator-only screens: account creation, approvals, suspensions and
| role changes. Staff and residents are turned away by the role middleware.
|
*/
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
        Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
        Route::post('/accounts/{user}/approve', [AccountController::class, 'approve'])->name('accounts.approve');
        Route::post('/accounts/{user}/reject', [AccountController::class, 'reject'])->name('accounts.reject');
        Route::post('/accounts/{user}/suspend', [AccountController::class, 'suspend'])->name('accounts.suspend');
        Route::post('/accounts/{user}/reactivate', [AccountController::class, 'reactivate'])->name('accounts.reactivate');
        Route::patch('/accounts/{user}/role', [AccountController::class, 'changeRole'])->name('accounts.role');
    });
