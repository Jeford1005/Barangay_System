<?php

use App\Http\Controllers\AdminExportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Administrative CSV exports
|--------------------------------------------------------------------------
|
| This route file is intentionally standalone so the primary integration can
| require it from routes/web.php without coupling the export slice to any
| module index view. Every route is protected by the existing auth/admin
| middleware aliases.
|
*/

$exportTypes = [
    'residents' => 'residents',
    'households' => 'households',
    'blotter' => 'blotter',
    'welfare' => 'welfare',
    'certificates' => 'certificates',
];

Route::middleware(['auth', 'admin'])
    ->prefix('admin/exports')
    ->group(function () use ($exportTypes): void {
        foreach ($exportTypes as $dataset => $method) {
            // Put the complete name in the action before Route::get() adds
            // the route to the collection; this keeps route() and the test
            // application's name lookup deterministic.
            Route::get('/'.$dataset, [
                'uses' => AdminExportController::class.'@'.$method,
                'as' => 'admin.exports.'.$dataset,
            ]);
        }
    });

// Module-level aliases make it easy for an existing module toolbar to link to
// an export without changing the route shape of the other admin modules.
Route::middleware(['auth', 'admin'])->group(function () use ($exportTypes): void {
    foreach ($exportTypes as $dataset => $method) {
        Route::get('/'.$dataset.'/export', [
            'uses' => AdminExportController::class.'@'.$method,
            'as' => $dataset.'.export',
        ]);
    }
});
