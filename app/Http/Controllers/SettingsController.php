<?php

namespace App\Http\Controllers;

use App\Jobs\CreateDatabaseBackup;
use App\Models\AuditLog;
use App\Models\BackupRun;
use App\Services\BackupService;
use App\Services\SystemHealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SettingsController extends Controller
{
    /**
     * Settings has no hub page: the sidebar link and any bookmarked
     * /admin/settings land on the first section instead of a screen that
     * only restated the section list. The query string (notably the dialog's
     * ?embed=1) travels with the redirect so the frame keeps rendering
     * without the app chrome.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.users.index', request()->query());
    }

    public function maintenance(BackupService $backups, SystemHealthService $health): View
    {
        return view('admin.settings.maintenance', [
            'backups' => $backups->all(),
            'system' => $this->systemStatus(),
            'health' => $health->check(),
        ]);
    }

    public function storeBackup(Request $request): RedirectResponse
    {
        // The audit entry is written only after the run row exists and the
        // job dispatched: auditing first would log backups that never
        // happened whenever creation or dispatch throws.
        $backupRun = BackupRun::create([
            'requested_by' => $request->user()?->id,
            'status' => 'Queued',
        ]);

        try {
            CreateDatabaseBackup::dispatch(
                $request->user()?->id,
                $request->user()?->email,
                $request->ip(),
                $request->userAgent(),
                $backupRun->id,
            );
        } catch (\Throwable $exception) {
            $backupRun->update([
                'status' => 'Failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);
            report($exception);

            return back()->withErrors(['backup' => $exception->getMessage()]);
        }

        $this->recordAudit($request, 'system.backup_requested', [
            'backup_run_id' => $backupRun->id,
        ]);

        return back()->with('success', 'Database backup queued successfully.');
    }

    public function downloadBackup(Request $request, BackupService $backups, string $backup): BinaryFileResponse
    {
        // path() 404s on unknown names before anything is logged. The
        // response is built first so the audit entry is recorded only when
        // the download will actually be served — a file vanishing between
        // the lookup and the download must not leave a false audit trail.
        $path = $backups->path($backup);
        $response = response()->download($path, basename($path), [
            'Content-Type' => 'application/octet-stream',
        ]);

        $this->recordAudit($request, 'system.backup_downloaded', [
            'file' => basename($path),
        ]);

        return $response;
    }

    public function deleteBackup(Request $request, BackupService $backups, string $backup): RedirectResponse
    {
        $name = basename($backup);
        $backups->delete($name);
        $this->recordAudit($request, 'system.backup_deleted', [
            'file' => $name,
        ]);

        return back()->with('success', 'Backup deleted.');
    }

    public function clearCache(Request $request): RedirectResponse
    {
        // Throttle rapid repeated clears (double-clicks, refresh-resubmits).
        // The form already asks for confirmation (data-confirm); this is the
        // server-side half. The hit is recorded AFTER the flush on purpose:
        // cache:clear wipes the default store, so a hit taken before it
        // would be erased along with everything else it just cleared.
        $key = 'cache-clear:'.($request->user()?->getKey() ?? $request->ip());

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return back()->withErrors([
                'cache' => 'Caches were just cleared. Wait '.RateLimiter::availableIn($key).' seconds before clearing again.',
            ]);
        }

        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        RateLimiter::hit($key, 30);
        $this->recordAudit($request, 'system.cache_cleared');

        return back()->with('success', 'Application and view caches cleared.');
    }

    /**
     * @return array<string, string|bool|int>
     */
    private function systemStatus(): array
    {
        $databaseConnected = false;

        try {
            DB::select('select 1');
            $databaseConnected = true;
        } catch (\Throwable) {
            $databaseConnected = false;
        }

        $storageFree = @disk_free_space(storage_path());

        return [
            'environment' => (string) config('app.env'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database' => (string) config('database.default'),
            'database_connected' => $databaseConnected,
            'mail' => (string) config('mail.default'),
            'cache' => (string) config('cache.default'),
            'session' => (string) config('session.driver'),
            'queue' => (string) config('queue.default'),
            // `null` means the free space could not be determined, which is
            // different from a real zero — the view renders "unknown" for it.
            'storage_free' => $storageFree === false ? null : (int) $storageFree,
        ];
    }

    private function recordAudit(Request $request, string $event, array $properties = []): void
    {
        $actor = $request->user();

        AuditLog::record(
            $event,
            $actor?->id,
            $actor?->email,
            $request->ip(),
            $request->userAgent(),
            array_merge(['source' => 'admin'], $properties),
        );
    }
}
