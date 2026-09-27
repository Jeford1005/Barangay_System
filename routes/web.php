<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Guests land on the sign-in page; signed-in users land on the dashboard,
| which renders as admin / staff / resident depending on the account role.
| Feature modules are added in routes/modules/*.php as they are built.
|
*/

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware('auth')
    ->name('dashboard');

require __DIR__.'/auth.php';
