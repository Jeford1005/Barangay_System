<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Guests land on the sign-in page, office users (admin/staff) on the office
| dashboard, residents on their self-service portal. Each feature module owns
| a file in routes/modules/ — access is enforced by the admin / permission /
| resident middleware aliases.
|
*/

Route::get('/', function () {
    $user = auth()->user();

    if ($user === null) {
        return redirect()->route('login');
    }

    return redirect()->route($user->isResident() ? 'resident.portal' : 'dashboard');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware('auth')
    ->name('dashboard');

require __DIR__.'/auth.php';

require __DIR__.'/modules/puroks.php';
require __DIR__.'/modules/residents.php';
require __DIR__.'/modules/households.php';
require __DIR__.'/modules/certificates.php';
require __DIR__.'/modules/blotter.php';
require __DIR__.'/modules/welfare.php';
require __DIR__.'/modules/reports.php';
require __DIR__.'/modules/portal.php';
require __DIR__.'/modules/admin.php';
