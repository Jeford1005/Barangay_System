<?php

use App\Http\Controllers\AccountApprovalController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BlotterController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CertificateRequestAdminController;
use App\Http\Controllers\CertificateTypeController;
use App\Http\Controllers\HouseholdController;
use App\Http\Controllers\MailHealthController;
use App\Http\Controllers\PurokController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResidentBlotterController;
use App\Http\Controllers\ResidentCertificateRequestController;
use App\Http\Controllers\ResidentController;
use App\Http\Controllers\ResidentOfficialsController;
use App\Http\Controllers\ResidentPhotoController;
use App\Http\Controllers\ResidentPortalController;
use App\Http\Controllers\ResidentRecordChangeController;
use App\Http\Controllers\ResidentWelfareController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserAccountController;
use App\Http\Controllers\WelfareController;
use App\Models\Blotter;
use App\Models\CertificateIssuance;
use App\Models\CertificateRequest;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\ResidentRecordChange;
use App\Models\User;
use App\Models\Welfare;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These routes
| are loaded by the ServiceProvider within the application's kernel. If you
| want to totally separate the API routes from web routes, you can just have
| a single controller to manage all web views and you're building a
| traditional Laravel-based application, feel free to tell Hire the stream
| router to this provider instead of the default router.
|
*/

// Internal barangay system: the root URL routes by role —
// guests to sign-in, signed-in users straight to their workspace.
Route::get('/', function () {
    if (! auth()->check()) {
        // Render sign-in at the domain root instead of bouncing to /login, so
        // the address bar shows the bare URL rather than an internal path.
        // /login still exists and remains what every middleware redirect and
        // "Sign in" link targets; the controller owns the view data (puroks,
        // households, reset cooldown), so this stays a thin alias for it.
        return app(AuthenticatedSessionController::class)->create();
    }

    return redirect()->route(
        auth()->user()->user_type === 'resident' ? 'resident.portal' : 'dashboard'
    );
});

// Login/throttled routes - add rate limiting protection
Route::middleware(['auth', 'verified', 'throttle:60,1'])->group(function () {
    Route::get('/dashboard', function () {
        $user = auth()->user();

        if ($user->user_type === 'resident') {
            return redirect()->route('resident.portal');
        }

        abort_unless($user->isOfficeUser(), 403);

        $monthStart = now()->startOfMonth();

        // Work waiting on the office. Each count below already backs a sidebar
        // badge or an analytics slice - the dashboard surfaces counts the app
        // computes anyway instead of inventing parallel ones.
        $queues = [
            ['label' => 'Certificate requests', 'count' => CertificateRequest::pending()->count(), 'href' => route('admin.certificate-requests.index'), 'critical' => false],
            ['label' => 'Resident corrections', 'count' => ResidentRecordChange::where('status', 'Pending')->count(), 'href' => route('admin.resident-changes.index'), 'critical' => false],
            ['label' => 'Welfare requests', 'count' => Welfare::whereIn('status', ['Requested', 'Under Review'])->count(), 'href' => route('welfare.index'), 'critical' => false],
            ['label' => 'Open blotter cases', 'count' => Blotter::whereIn('status', ['Open', 'Pending'])->count(), 'href' => route('blotter.index'), 'critical' => true],
        ];

        // Account approvals sit behind the admin middleware, so a staff member
        // following the link would land on a 403 - only offer it to admins.
        if ($user->isAdmin()) {
            $queues[] = ['label' => 'Account approvals', 'count' => User::where('user_type', 'resident')->where('status', 'pending')->count(), 'href' => route('admin.approvals.index'), 'critical' => false];
        }

        return view('dashboard', [
            'residentCount' => Resident::count(),
            'householdCount' => Household::count(),
            'purokCount' => Purok::count(),
            'issuedTotal' => CertificateIssuance::issued()->count(),
            'issuedMonth' => CertificateIssuance::issued()->where('created_at', '>=', $monthStart)->count(),
            'newResidentsMonth' => Resident::where('created_at', '>=', $monthStart)->count(),
            'newHouseholdsMonth' => Household::where('created_at', '>=', $monthStart)->count(),
            'queues' => $queues,
            'attentionTotal' => array_sum(array_column($queues, 'count')),
        ]);
    })->name('dashboard');
});

// Administrative and operational modules - gated by role/permission middleware
Route::middleware(['auth'])->group(function () {
    Route::get('/admin/mail-health', [MailHealthController::class, 'show'])
        ->middleware('admin')
        ->name('admin.mail.health');

    // Resident account approvals (admin only)
    Route::middleware('admin')->prefix('admin/approvals')->name('admin.approvals.')->group(function () {
        Route::get('/', [AccountApprovalController::class, 'index'])->name('index');
        Route::post('/{user}/approve', [AccountApprovalController::class, 'approve'])->name('approve');
        Route::post('/{user}/reject', [AccountApprovalController::class, 'reject'])->name('reject');
    });

    // Settings and system maintenance (admin only)
    Route::middleware('admin')->prefix('admin/settings')->name('admin.settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::get('/maintenance', [SettingsController::class, 'maintenance'])->name('maintenance');
        Route::post('/backups', [SettingsController::class, 'storeBackup'])
            ->middleware('throttle:5,1')
            ->name('backups.store');
        Route::get('/backups/{backup}/download', [SettingsController::class, 'downloadBackup'])->name('backups.download');
        Route::delete('/backups/{backup}', [SettingsController::class, 'deleteBackup'])->name('backups.destroy');
        Route::post('/cache/clear', [SettingsController::class, 'clearCache'])->name('cache.clear');
    });

    // User account directory and access management (admin only)
    Route::middleware('admin')->prefix('admin/users')->name('admin.users.')->group(function () {
        Route::get('/', [UserAccountController::class, 'index'])->name('index');
        Route::post('/{user}/resident-profile-from-application', [UserAccountController::class, 'createResidentFromApplication'])->name('resident-profile-from-application');
        Route::post('/{user}/resident-unlink', [UserAccountController::class, 'unlinkResident'])->name('resident-unlink');
        Route::patch('/{user}/resident-link', [UserAccountController::class, 'linkResident'])->name('resident-link');
        Route::patch('/{user}/role', [UserAccountController::class, 'updateRole'])->name('role');
        Route::post('/{user}/suspend', [UserAccountController::class, 'suspend'])->name('suspend');
        Route::post('/{user}/reactivate', [UserAccountController::class, 'reactivate'])->name('reactivate');
        Route::post('/{user}/reset-code', [UserAccountController::class, 'sendResetCode'])
            ->middleware('throttle:10,1')
            ->name('reset');
        Route::get('/{user}/edit', [UserAccountController::class, 'edit'])->name('edit');
        Route::patch('/{user}', [UserAccountController::class, 'update'])->name('update');
        Route::get('/{user}', [UserAccountController::class, 'show'])->name('show');
    });

    Route::post('/admin/mail-health/send-test', [MailHealthController::class, 'sendTest'])
        ->middleware(['admin', 'throttle:5,1'])
        ->name('admin.mail.test');
    Route::get('/admin/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('admin')
        ->name('admin.audit-logs.index');

    Route::middleware('admin')
        ->prefix('archive')->name('archive.')->group(function () {
            Route::get('/', [ArchiveController::class, 'index'])->name('index');
            Route::get('/{type}', [ArchiveController::class, 'index'])->name('type');
            Route::post('/{type}/{id}/restore', [ArchiveController::class, 'restore'])->name('restore');
            Route::delete('/{type}/{id}', [ArchiveController::class, 'destroy'])->name('destroy');
        });

    Route::middleware('permission:analytics.view')
        ->prefix('analytics')->name('analytics.')->group(function () {
            Route::get('/', [AnalyticsController::class, 'index'])->name('index');
        });

    Route::middleware('admin')
        ->prefix('admin/certificate-types')->name('admin.certificate-types.')->group(function () {
            Route::get('/', [CertificateTypeController::class, 'index'])->name('index');
            Route::get('/create', [CertificateTypeController::class, 'create'])->name('create');
            Route::post('/', [CertificateTypeController::class, 'store'])->name('store');
            Route::get('/{document}/edit', [CertificateTypeController::class, 'edit'])->name('edit');
            Route::put('/{document}', [CertificateTypeController::class, 'update'])->name('update');
            Route::delete('/{document}', [CertificateTypeController::class, 'destroy'])->name('destroy');
        });

    Route::middleware('permission:reports.view')
        ->prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [ReportController::class, 'index'])->name('index');
            Route::get('/population', [ReportController::class, 'population'])->name('population');
            Route::get('/blotter', [ReportController::class, 'blotter'])->name('blotter');
            Route::get('/welfare', [ReportController::class, 'welfare'])->name('welfare');
        });

    Route::middleware('permission:puroks.view')
        ->prefix('puroks')->name('puroks.')->group(function () {
            Route::get('/', [PurokController::class, 'index'])->name('index');
            Route::get('/create', [PurokController::class, 'create'])
                ->middleware('admin')
                ->name('create');
            Route::post('/', [PurokController::class, 'store'])
                ->middleware('admin')
                ->name('store');
            Route::get('/{purok}/edit', [PurokController::class, 'edit'])
                ->middleware('admin')
                ->name('edit');
            Route::put('/{purok}', [PurokController::class, 'update'])
                ->middleware('admin')
                ->name('update');
            Route::delete('/{purok}', [PurokController::class, 'destroy'])
                ->middleware('admin')
                ->name('destroy');
        });

    Route::middleware('permission:residents.view')
        ->prefix('residents')->name('residents.')->group(function () {
            Route::get('/', [ResidentController::class, 'index'])->name('index');
            Route::get('/directory', [ResidentController::class, 'directory'])->name('directory');
            Route::get('/create', [ResidentController::class, 'create'])
                ->middleware('permission:residents.manage')
                ->name('create');
            Route::post('/', [ResidentController::class, 'store'])
                ->middleware('permission:residents.manage')
                ->name('store');
            Route::get('/{resident}/edit', [ResidentController::class, 'edit'])
                ->middleware('permission:residents.manage')
                ->name('edit');
            Route::post('/{resident}/archive', [ResidentController::class, 'archive'])
                ->middleware('admin')
                ->name('archive');
            Route::post('/{resident}/restore', [ResidentController::class, 'restore'])
                ->middleware('admin')
                ->name('restore');
            Route::put('/{resident}', [ResidentController::class, 'update'])
                ->middleware('permission:residents.manage')
                ->name('update');
        });

    // Photos are personal data, so they are streamed through an authorized
    // route instead of a public /storage URL. This sits outside the
    // `residents.view` permission group because the owning resident is allowed
    // to see their own photo; the controller holds the ownership rule.
    Route::middleware('auth')
        ->get('/residents/{resident}/photo', ResidentPhotoController::class)
        ->name('residents.photo');

    Route::middleware('permission:households.view')
        ->prefix('households')->name('households.')->group(function () {
            Route::get('/', [HouseholdController::class, 'index'])->name('index');
            Route::get('/create', [HouseholdController::class, 'create'])
                ->middleware('permission:households.manage')
                ->name('create');
            Route::post('/', [HouseholdController::class, 'store'])
                ->middleware('permission:households.manage')
                ->name('store');
            Route::get('/{household}/edit', [HouseholdController::class, 'edit'])
                ->middleware('permission:households.manage')
                ->name('edit');
            Route::put('/{household}', [HouseholdController::class, 'update'])
                ->middleware('permission:households.manage')
                ->name('update');
            Route::delete('/{household}', [HouseholdController::class, 'destroy'])
                ->middleware('admin')
                ->name('destroy');
        });

    Route::middleware('permission:blotter.view')
        ->prefix('blotter')->name('blotter.')->group(function () {
            Route::get('/', [BlotterController::class, 'index'])->name('index');
            Route::get('/create', [BlotterController::class, 'create'])
                ->middleware('permission:blotter.manage')
                ->name('create');
            Route::post('/', [BlotterController::class, 'store'])
                ->middleware('permission:blotter.manage')
                ->name('store');
            Route::get('/{blotter}/print', [BlotterController::class, 'printSheet'])
                ->middleware('permission:blotter.manage')
                ->name('print');
            Route::get('/{blotter}/edit', [BlotterController::class, 'edit'])
                ->middleware('permission:blotter.manage')
                ->name('edit');
            Route::put('/{blotter}', [BlotterController::class, 'update'])
                ->middleware('permission:blotter.manage')
                ->name('update');
            Route::delete('/{blotter}', [BlotterController::class, 'destroy'])
                ->middleware('admin')
                ->name('destroy');
        });

    Route::middleware('permission:certificates.view')
        ->prefix('certificates')->name('certificates.')->group(function () {
            Route::get('/', [CertificateController::class, 'index'])->name('index');
            Route::get('/create', [CertificateController::class, 'create'])
                ->middleware('permission:certificates.issue')
                ->name('create');
            Route::post('/', [CertificateController::class, 'store'])
                ->middleware('permission:certificates.issue')
                ->name('store');
            Route::get('/{issuance}/print', [CertificateController::class, 'print'])
                ->middleware('permission:certificates.issue')
                ->name('print');
            Route::post('/{issuance}/void', [CertificateController::class, 'void'])
                ->middleware('admin')
                ->name('void');
        });

    Route::middleware('permission:welfare.view')
        ->prefix('welfare')->name('welfare.')->group(function () {
            Route::get('/', [WelfareController::class, 'index'])->name('index');
            Route::get('/create', [WelfareController::class, 'create'])
                ->middleware('permission:welfare.intake')
                ->name('create');
            Route::post('/', [WelfareController::class, 'store'])
                ->middleware('permission:welfare.intake')
                ->name('store');
            Route::get('/{welfare}/edit', [WelfareController::class, 'edit'])
                ->middleware('permission:welfare.approve')
                ->name('edit');
            Route::put('/{welfare}', [WelfareController::class, 'update'])
                ->middleware('permission:welfare.approve')
                ->name('update');
            Route::delete('/{welfare}', [WelfareController::class, 'destroy'])
                ->middleware('admin')
                ->name('destroy');
        });
});

// Resident profile correction review (staff may view; officials decide)
Route::middleware(['auth', 'permission:resident-changes.view'])->prefix('admin/resident-changes')->name('admin.resident-changes.')->group(function () {
    Route::get('/', [ResidentRecordChangeController::class, 'indexForAdmin'])->name('index');
    Route::post('/{change}/approve', [ResidentRecordChangeController::class, 'approve'])
        ->middleware('permission:resident-changes.decide')
        ->name('approve');
    Route::post('/{change}/reject', [ResidentRecordChangeController::class, 'reject'])
        ->middleware('permission:resident-changes.decide')
        ->name('reject');
});

// Resident portal - gated by role via the 'resident' middleware alias
Route::middleware(['auth', 'resident'])->group(function () {
    Route::get('/my', [ResidentPortalController::class, 'index'])->name('resident.portal');
    Route::put('/my/contact', [ResidentPortalController::class, 'updateContact'])->name('resident.contact.update');

    // The resident's own photo. Staff use residents.photo; this entry point
    // resolves the profile from the signed-in account.
    Route::get('/my/photo', [ResidentPhotoController::class, 'mine'])->name('resident.photo');

    // Online certificate requests
    Route::get('/my/requests', [ResidentCertificateRequestController::class, 'index'])->name('resident.requests');
    Route::get('/my/requests/{certificateRequest}/certificate', [ResidentCertificateRequestController::class, 'certificate'])->name('resident.requests.certificate');
    Route::post('/my/requests', [ResidentCertificateRequestController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('resident.requests.store');
    Route::post('/my/requests/{certificateRequest}/cancel', [ResidentCertificateRequestController::class, 'cancel'])
        ->middleware('throttle:15,1')
        ->name('resident.requests.cancel');

    Route::get('/my/changes', [ResidentRecordChangeController::class, 'index'])->name('resident.changes');
    Route::post('/my/changes', [ResidentRecordChangeController::class, 'store'])->name('resident.changes.store');
    Route::post('/my/changes/{change}/cancel', [ResidentRecordChangeController::class, 'cancel'])->name('resident.changes.cancel');

    // Read-only directory of the officials serving the barangay, drawn from
    // the same reference table the certificate signatures come from.
    Route::get('/my/officials', [ResidentOfficialsController::class, 'index'])->name('resident.officials');

    // Incident reports and assistance requests. Both write straight into the
    // office's own blotter and welfare queues instead of a parallel one, so
    // there is a single queue per module for staff to work through.
    Route::get('/my/blotter', [ResidentBlotterController::class, 'index'])->name('resident.blotter');
    Route::post('/my/blotter', [ResidentBlotterController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('resident.blotter.store');

    Route::get('/my/welfare', [ResidentWelfareController::class, 'index'])->name('resident.welfare');
    Route::post('/my/welfare', [ResidentWelfareController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('resident.welfare.store');
});

// Office queue for online certificate requests (staff may view; officials decide)
Route::middleware(['auth', 'permission:certificate-requests.view'])->prefix('admin/certificate-requests')->name('admin.certificate-requests.')->group(function () {
    Route::get('/', [CertificateRequestAdminController::class, 'index'])->name('index');
    Route::post('/{certificateRequest}/approve', [CertificateRequestAdminController::class, 'approve'])
        ->middleware('permission:certificate-requests.decide')
        ->name('approve');
    Route::post('/{certificateRequest}/reject', [CertificateRequestAdminController::class, 'reject'])
        ->middleware('permission:certificate-requests.decide')
        ->name('reject');
});

require __DIR__.'/admin-exports.php';

// Auth routes (login, register, password reset, verification)
require __DIR__.'/auth.php';
