<?php

namespace App\Http\Controllers;

use App\Jobs\CreateDatabaseBackup;
use App\Models\AuditLog;
use App\Models\BackupRun;
use App\Models\User;
use App\Services\BackupService;
use App\Services\SystemHealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('admin.settings.index', [
            'pendingApprovals' => User::where('user_type', 'resident')->where('status', 'pending')->count(),
        ]);
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
        $this->recordAudit($request, 'system.backup_requested');

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

        return back()->with('success', 'Database backup queued successfully.');
    }

    public function downloadBackup(Request $request, BackupService $backups, string $backup): BinaryFileResponse
    {
        $path = $backups->path($backup);
        $this->recordAudit($request, 'system.backup_downloaded', [
            'file' => basename($path),
        ]);

        return response()->download($path, basename($path), [
            'Content-Type' => 'application/octet-stream',
        ]);
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
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
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
