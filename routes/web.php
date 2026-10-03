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
use App\Http\Controllers\CertificateVerifyController;
use App\Http\Controllers\CleanupDriveController;
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
use App\Models\CleanupDrive;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\ResidentRecordChange;
use App\Models\User;
use App\Models\Welfare;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

// The test-email endpoint gets its own limiter bucket: the enclosing office
// group already applies throttle:60,1, and two numeric layers would share one
// counter (each request burns a hit per layer, so the 5/min ceiling tripped
// on the 3rd request). The named limiter keeps an effective 5/min per admin.
RateLimiter::for('mailhealth', function (Request $request) {
    return Limit::perMinute(5)->by('mailhealth:'.($request->user()?->getAuthIdentifier() ?? $request->ip()));
});

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

// Public certificate verification — the target of the QR printed on
// certificates. Guest-only (no auth middleware) and throttled to slow
// control-number enumeration; the controller always answers 200 (never
// 404) so scanners see a friendly valid/void/not-found result.
Route::middleware('throttle:30,1')->get('/verify/{control_number}/{token}', [CertificateVerifyController::class, 'show'])
    ->name('certificates.verify');

// Public cleanup drive sheet — target of the venue QR on the logbook
// print. Guest-only (no auth middleware), URL-signed, throttled like
// verify. Aggregates only, never volunteer names.
Route::middleware(['signed', 'throttle:30,1'])->get('/cleanup/{drive}/sheet', [CleanupDriveController::class, 'sheet'])
    ->name('cleanup.sheet');

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
            // Drives still on the office's plate: scheduled plus already
            // running. Finished (Completed/Cancelled) drives need no action.
            ['label' => 'Scheduled cleanups', 'count' => CleanupDrive::whereIn('status', ['Scheduled', 'Ongoing'])->count(), 'href' => route('cleanup.index'), 'critical' => false],
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

// Administrative and operational modules - gated by role/permission middleware.
// `verified` is a no-op until User implements MustVerifyEmail (see
// EnsureEmailIsVerified: it only blocks MustVerifyEmail instances), so seeded
// logins keep working. The group throttle rate-limits direct-URL bypass
// attempts against every office route at once.
Route::middleware(['auth', 'verified', 'throttle:60,1'])->group(function () {
    Route::get('/admin/mail-health', [MailHealthController::class, 'show'])
        ->middleware('admin')
        ->name('admin.mail.health');

    // Resident account approvals (admin only)
    Route::middleware('admin')->prefix('admin/approvals')->name('admin.approvals.')->group(function () {
        Route::get('/', [AccountApprovalController::class, 'index'])->name('index');
        Route::post('/{user}/approve', [AccountApprovalController::class, 'approve'])
            ->middleware('throttle:30,1')
            ->name('approve');
        Route::post('/{user}/reject', [AccountApprovalController::class, 'reject'])
            ->middleware('throttle:30,1')
            ->name('reject');
    });

    // Settings and system maintenance (admin only)
    Route::middleware('admin')->prefix('admin/settings')->name('admin.settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::get('/maintenance', [SettingsController::class, 'maintenance'])->name('maintenance');
        Route::post('/backups', [SettingsController::class, 'storeBackup'])
            ->middleware('throttle:5,1')
            ->name('backups.store');
        Route::get('/backups/{backup}/download', [SettingsController::class, 'downloadBackup'])
            ->middleware('throttle:10,1')
            ->name('backups.download');
        Route::delete('/backups/{backup}', [SettingsController::class, 'deleteBackup'])
            ->middleware('throttle:15,1')
            ->name('backups.destroy');
        Route::post('/cache/clear', [SettingsController::class, 'clearCache'])
            ->middleware('throttle:5,1')
            ->name('cache.clear');
    });

    // User account directory and access management (admin only)
    Route::middleware('admin')->prefix('admin/users')->name('admin.users.')->group(function () {
        Route::get('/', [UserAccountController::class, 'index'])->name('index');
        // Creation lives above the {user} show route so /create never
        // resolves as a user id.
        Route::get('/create', [UserAccountController::class, 'create'])->name('create');
        Route::post('/', [UserAccountController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('store');
        Route::post('/{user}/resident-profile-from-application', [UserAccountController::class, 'createResidentFromApplication'])->name('resident-profile-from-application');
        Route::post('/{user}/resident-unlink', [UserAccountController::class, 'unlinkResident'])->name('resident-unlink');
        // Link/role/identity edits accept both PATCH and PUT so proxied or
        // hand-rolled clients sending PUT don't hit a 405; Blade keeps
        // sending POST+@method('PATCH'), which still matches.
        Route::match(['PATCH', 'PUT'], '/{user}/resident-link', [UserAccountController::class, 'linkResident'])->name('resident-link');
        Route::match(['PATCH', 'PUT'], '/{user}/role', [UserAccountController::class, 'updateRole'])->name('role');
        Route::post('/{user}/suspend', [UserAccountController::class, 'suspend'])->name('suspend');
        Route::post('/{user}/reactivate', [UserAccountController::class, 'reactivate'])->name('reactivate');
        Route::post('/{user}/reset-code', [UserAccountController::class, 'sendResetCode'])
            ->middleware('throttle:10,1')
            ->name('reset');
        Route::get('/{user}/edit', [UserAccountController::class, 'edit'])->name('edit');
        Route::match(['PATCH', 'PUT'], '/{user}', [UserAccountController::class, 'update'])->name('update');
        Route::get('/{user}', [UserAccountController::class, 'show'])->name('show');
    });

    // Email 2FA fallback toggle. Deliberately outside the `admin` middleware
    // group (but on the same URL/name pattern): the group redirects
    // non-admins to the dashboard, while this action must answer 403 via the
    // controller's admin abort — like every other method in
    // UserAccountController. Guests still bounce to login via `auth`.
    Route::middleware('auth')->prefix('admin/users')->name('admin.users.')->group(function () {
        Route::match(['PATCH', 'PUT'], '/{user}/email-otp-fallback', [UserAccountController::class, 'updateEmailOtpFallback'])
            ->middleware('throttle:10,1')
            ->name('email-otp-fallback');
    });

    Route::post('/admin/mail-health/send-test', [MailHealthController::class, 'sendTest'])
        ->middleware(['admin', 'throttle:mailhealth'])
        ->name('admin.mail.test');
    Route::get('/admin/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('admin')
        ->name('admin.audit-logs.index');

    Route::middleware('admin')
        ->prefix('archive')->name('archive.')->group(function () {
            Route::get('/', [ArchiveController::class, 'index'])->name('index');
            // Constrain {type} to the ArchiveController::TYPES keys so this
            // wildcard cannot shadow future subpaths and unknown types 404 at
            // the router (the controller 404s them too - defence in depth).
            Route::get('/{type}', [ArchiveController::class, 'index'])
                ->whereIn('type', ['residents', 'households', 'blotter', 'welfare', 'cleanup'])
                ->name('type');
            Route::post('/{type}/{id}/restore', [ArchiveController::class, 'restore'])
                ->middleware('throttle:30,1')
                ->whereIn('type', ['residents', 'households', 'blotter', 'welfare', 'cleanup'])
                ->name('restore');
            Route::delete('/{type}/{id}', [ArchiveController::class, 'destroy'])
                ->middleware('throttle:15,1')
                ->whereIn('type', ['residents', 'households', 'blotter', 'welfare', 'cleanup'])
                ->name('destroy');
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
                ->withTrashed()
                ->name('edit');
            // Archive/restore stay POST: the directory Blade forms submit plain
            // POST with no @method spoof, and ResidentCrudTest/StaffAccessTest/
            // OfficialAccessTest pin POST. Moving to DELETE needs Blade + test
            // edits (REPORTED, not done here).
            Route::post('/{resident}/archive', [ResidentController::class, 'archive'])
                ->middleware(['admin', 'throttle:30,1'])
                ->name('archive');
            Route::post('/{resident}/restore', [ResidentController::class, 'restore'])
                ->middleware(['admin', 'throttle:30,1'])
                ->withTrashed()
                ->name('restore');
            Route::put('/{resident}', [ResidentController::class, 'update'])
                ->middleware('permission:residents.manage')
                ->withTrashed()
                ->name('update');
        });

    // Photos are personal data, so they are streamed through an authorized
    // route instead of a public /storage URL. This sits outside the
    // `residents.view` permission group because the owning resident is allowed
    // to see their own photo; the controller holds the ownership rule
    // (staff with residents.view OR $resident->user_id === auth id) — a `can:`
    // gate would need a new Policy (REPORTED, not added here), and `signed`
    // would break the unsigned <img> URLs in resident/_form.blade.php.
    // The throttle slows sequential-ID enumeration scrapes.
    Route::middleware(['auth', 'verified', 'throttle:30,1'])
        ->get('/residents/{resident}/photo', ResidentPhotoController::class)
        ->withTrashed()
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
            Route::post('/{issuance}/restore', [CertificateController::class, 'restore'])
                ->middleware('admin')
                ->name('restore');
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

    // Cleanup drives (office-managed; officials read, staff/admin manage).
    // Write endpoints carry tight throttles like the sibling modules:
    // sign-ups and intake at 10/min, edits and deletes at 15/min. There is
    // deliberately no show route here: show() stays ready in the controller
    // for the venue-QR / public-sheet workstream to mount its own routes.
    Route::middleware('permission:cleanup.view')
        ->prefix('cleanup')->name('cleanup.')->group(function () {
            Route::get('/', [CleanupDriveController::class, 'index'])->name('index');
            Route::get('/{drive}/logbook', [CleanupDriveController::class, 'logbook'])->name('logbook');
            Route::get('/create', [CleanupDriveController::class, 'create'])
                ->middleware('permission:cleanup.manage')
                ->name('create');
            Route::post('/', [CleanupDriveController::class, 'store'])
                ->middleware(['permission:cleanup.manage', 'throttle:10,1'])
                ->name('store');
            Route::post('/{drive}/join', [CleanupDriveController::class, 'join'])
                ->middleware(['permission:cleanup.manage', 'throttle:10,1'])
                ->name('join');
            Route::post('/{drive}/participants/{participant}/check-in', [CleanupDriveController::class, 'checkIn'])
                ->middleware(['permission:cleanup.manage', 'throttle:15,1'])
                ->name('check-in');
            Route::get('/{drive}/edit', [CleanupDriveController::class, 'edit'])
                ->middleware('permission:cleanup.manage')
                ->name('edit');
            Route::put('/{drive}', [CleanupDriveController::class, 'update'])
                ->middleware(['permission:cleanup.manage', 'throttle:15,1'])
                ->name('update');
            Route::delete('/{drive}', [CleanupDriveController::class, 'destroy'])
                ->middleware(['admin', 'throttle:15,1'])
                ->name('destroy');
        });
});

// Resident profile correction review (staff may view; officials decide)
Route::middleware(['auth', 'verified', 'permission:resident-changes.view', 'throttle:60,1'])->prefix('admin/resident-changes')->name('admin.resident-changes.')->group(function () {
    Route::get('/', [ResidentRecordChangeController::class, 'indexForAdmin'])->name('index');
    Route::post('/{change}/approve', [ResidentRecordChangeController::class, 'approve'])
        ->middleware(['permission:resident-changes.decide', 'throttle:30,1'])
        ->name('approve');
    Route::post('/{change}/reject', [ResidentRecordChangeController::class, 'reject'])
        ->middleware(['permission:resident-changes.decide', 'throttle:30,1'])
        ->name('reject');
});

// Resident portal - gated by role via the 'resident' middleware alias.
// `verified` is a future-proof no-op (see office group note above). The group
// throttle rate-limits direct-URL bypass; write endpoints add tighter limits.
Route::middleware(['auth', 'verified', 'resident', 'throttle:60,1'])->group(function () {
    Route::get('/my', [ResidentPortalController::class, 'index'])->middleware('resident.profile')->name('resident.portal');
    Route::put('/my/contact', [ResidentPortalController::class, 'updateContact'])
        ->middleware('throttle:30,1')
        ->name('resident.contact.update');

    // The resident's own photo. Staff use residents.photo; this entry point
    // resolves the profile from the signed-in account.
    Route::get('/my/photo', [ResidentPhotoController::class, 'mine'])->name('resident.photo');

    // The contact form can only replace a photo (it keys on hasFile), so
    // clearing one is a separate action with its own confirmation.
    Route::delete('/my/photo', [ResidentPortalController::class, 'destroyPhoto'])
        ->middleware('throttle:30,1')
        ->name('resident.photo.destroy');

    // Online certificate requests
    Route::get('/my/requests', [ResidentCertificateRequestController::class, 'index'])->middleware('resident.profile')->name('resident.requests');
    // Ownership is enforced in the controller (request must belong to the
    // sign-in's own profile and be Approved); the throttle slows
    // sequential-ID enumeration. A `can:` gate would need a new Policy
    // (REPORTED, not added here).
    Route::get('/my/requests/{certificateRequest}/certificate', [ResidentCertificateRequestController::class, 'certificate'])
        ->middleware('throttle:30,1')
        ->name('resident.requests.certificate');
    Route::post('/my/requests', [ResidentCertificateRequestController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('resident.requests.store');
    Route::post('/my/requests/{certificateRequest}/cancel', [ResidentCertificateRequestController::class, 'cancel'])
        ->middleware('throttle:15,1')
        ->name('resident.requests.cancel');

    // The correction form renders on the Profile page (?edit=1); only the
    // submission and cancellation endpoints remain here.
    Route::post('/my/changes', [ResidentRecordChangeController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('resident.changes.store');
    Route::post('/my/changes/{change}/cancel', [ResidentRecordChangeController::class, 'cancel'])
        ->middleware('throttle:15,1')
        ->name('resident.changes.cancel');

    // Read-only directory of the officials serving the barangay, drawn from
    // the same reference table the certificate signatures come from.
    Route::get('/my/officials', [ResidentOfficialsController::class, 'index'])->name('resident.officials');

    // Incident reports and assistance requests. Both write straight into the
    // office's own blotter and welfare queues instead of a parallel one, so
    // there is a single queue per module for staff to work through.
    Route::get('/my/blotter', [ResidentBlotterController::class, 'index'])->middleware('resident.profile')->name('resident.blotter');
    Route::post('/my/blotter', [ResidentBlotterController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('resident.blotter.store');

    Route::get('/my/welfare', [ResidentWelfareController::class, 'index'])->middleware('resident.profile')->name('resident.welfare');
    Route::post('/my/welfare', [ResidentWelfareController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('resident.welfare.store');

    // Community cleanups. The list shows Scheduled/Ongoing drives with a
    // Join button each, plus the resident's summed volunteer hours. The
    // self sign-up writes straight into the office's own participant
    // roster (no parallel queue), throttled like the sibling portal
    // writes; ownership comes from the session, so there is no
    // resident_id input. The GET carries resident.profile like the other
    // portal pages (friendly no-profile screen); the POST keeps the
    // controller abort because there is no page to show.
    Route::get('/my/cleanups', [CleanupDriveController::class, 'portal'])->middleware('resident.profile')->name('resident.cleanups');
    Route::post('/my/cleanups/{drive}/join', [CleanupDriveController::class, 'joinSelf'])
        ->middleware('throttle:10,1')
        ->name('resident.cleanups.join');
});

// Office queue for online certificate requests (staff may view; officials decide)
Route::middleware(['auth', 'verified', 'permission:certificate-requests.view', 'throttle:60,1'])->prefix('admin/certificate-requests')->name('admin.certificate-requests.')->group(function () {
    Route::get('/', [CertificateRequestAdminController::class, 'index'])->name('index');
    Route::post('/{certificateRequest}/approve', [CertificateRequestAdminController::class, 'approve'])
        ->middleware(['permission:certificate-requests.decide', 'throttle:30,1'])
        ->name('approve');
    Route::post('/{certificateRequest}/reject', [CertificateRequestAdminController::class, 'reject'])
        ->middleware(['permission:certificate-requests.decide', 'throttle:30,1'])
        ->name('reject');
});

require __DIR__.'/admin-exports.php';

// Auth routes (login, register, password reset, verification)
require __DIR__.'/auth.php';
