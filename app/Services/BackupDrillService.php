<?php

namespace App\Services;

use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use PDO;
use Throwable;

/**
 * Staging-restore dry runs for database backups.
 *
 * There is deliberately NO destructive live-DB restore anywhere in this
 * codebase (overwriting production from a web action or a console command
 * is unsafe). A drill instead restores the newest backup into a THROWAWAY
 * database under storage/app/private/backup-drills/, runs integrity checks
 * against the copy, records the outcome, and DELETES the scratch file.
 * The live database is opened read-only (VACUUM INTO / SELECT) and is
 * never written to by a drill.
 *
 * Outcome bookkeeping is an AuditLog event plus a `backup.last_verified_at`
 * cache timestamp — deliberately NOT a BackupRun row, so a drill can never
 * be mistaken for a real backup in the "Last backup run" display. Every
 * drill audit carries `drill: true` to stay distinguishable from real
 * backup/restore events.
 */
class BackupDrillService
{
    public const CACHE_KEY = 'backup.last_verified_at';

    public const CACHE_FILE_KEY = 'backup.last_verified_file';

    public const CACHE_DETAIL_KEY = 'backup.last_verified_detail';

    /**
     * Days after a passing drill before the maintenance page and
     * `system:health` start warning that a new drill is overdue.
     */
    public const STALE_AFTER_DAYS = 30;

    public function __construct(private readonly BackupService $backups) {}

    public function drillDirectory(): string
    {
        return storage_path('app'.DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.'backup-drills');
    }

    public static function lastVerifiedAt(): ?int
    {
        try {
            $value = Cache::get(self::CACHE_KEY);

            return is_numeric($value) ? (int) $value : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Reminder state for the maintenance page and `system:health`.
     *
     * @return array{state: string, verified_at: ?int, days_ago: ?int, display: string}
     */
    public static function statusFor(mixed $timestamp): array
    {
        $verifiedAt = is_numeric($timestamp) ? (int) $timestamp : null;

        if ($verifiedAt === null) {
            return [
                'state' => 'never',
                'verified_at' => null,
                'days_ago' => null,
                'display' => 'Never verified',
            ];
        }

        try {
            $at = Carbon::createFromTimestamp($verifiedAt);
            // Carbon 3 returns a float here (12.0000012); round so a drill
            // from "12 days ago" reads as 12, not 12.0000012.
            $daysAgo = max(0, (int) round($at->diffInDays(now())));
        } catch (Throwable) {
            return [
                'state' => 'never',
                'verified_at' => null,
                'days_ago' => null,
                'display' => 'Never verified',
            ];
        }

        return [
            'state' => $daysAgo > self::STALE_AFTER_DAYS ? 'stale' : 'recent',
            'verified_at' => $verifiedAt,
            'days_ago' => $daysAgo,
            'display' => $at->diffForHumans(),
        ];
    }

    /**
     * @return array{state: string, verified_at: ?int, days_ago: ?int, display: string}
     */
    public function drillStatus(): array
    {
        return self::statusFor(self::lastVerifiedAt());
    }

    /**
     * Verify the newest backup, or the named one when given.
     *
     * @return array{ok: bool, skipped: bool, file: ?string, format: string, tables: int, rows: int, message: string, error: ?string}
     */
    public function verifyNewest(?string $file = null): array
    {
        if ($file !== null && trim($file) !== '') {
            return $this->verifyNamed(trim($file));
        }

        $backups = $this->backups->all();

        if ($backups === []) {
            $this->audit('system.backup_drill_skipped', ['drill' => true, 'reason' => 'no backups exist']);

            return $this->result(false, true, null, 'none', 'No backups exist yet — create one first, then run the drill.');
        }

        return $this->verifyNamed($backups[0]['name']);
    }

    /**
     * @return array{ok: bool, skipped: bool, file: ?string, format: string, tables: int, rows: int, message: string, error: ?string}
     */
    public function verifyNamed(string $name): array
    {
        try {
            $path = $this->backups->path($name);
        } catch (Throwable) {
            return $this->result(false, false, basename($name), 'unknown', 'That backup does not exist.');
        }

        return $this->verifyFile($path, basename($path));
    }

    /**
     * @return array{ok: bool, skipped: bool, file: ?string, format: string, tables: int, rows: int, message: string, error: ?string}
     */
    public function verifyFile(string $path, string $name): array
    {
        if (! is_file($path) || filesize($path) === 0) {
            return $this->fail($name, 'sqlite', 'The backup file is empty, so there is nothing to restore.');
        }

        // Magic bytes win over the extension: a SQLite copy is verified by
        // actually opening it, whatever it happens to be named.
        if ($this->isSqliteFile($path)) {
            return $this->verifySqliteCopy($path, $name);
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        // A .sqlite name without SQLite magic is not an exotic format — the
        // snapshot itself is corrupt, and that must FAIL the drill loudly
        // rather than pass as a graceful skip.
        if ($extension === 'sqlite') {
            return $this->fail($name, 'sqlite', 'The backup is named like a SQLite snapshot but is not a valid SQLite database — it is corrupt.');
        }

        if ($extension === 'sql' && $this->looksLikeMysqlDump($path)) {
            return $this->verifyMysqlDump($path, $name);
        }

        // Anything else (a foreign dump, a renamed file) cannot be
        // throwaway-restored by this drill. Report it as skipped — never as
        // passed — so the reminder keeps nagging until a verifiable backup
        // exists.
        $this->audit('system.backup_drill_skipped', [
            'drill' => true,
            'file' => $name,
            'reason' => 'unsupported format for a throwaway restore',
        ]);

        return $this->result(
            false,
            true,
            $name,
            'unknown',
            "Skipped {$name}: this drill can verify SQLite snapshots and MySQL dumps only, so this format was left unverified."
        );
    }

    /**
     * Throwaway-restore a SQLite snapshot: copy it aside, open the COPY,
     * and run integrity checks against the copy only.
     *
     * @return array{ok: bool, skipped: bool, file: ?string, format: string, tables: int, rows: int, message: string, error: ?string}
     */
    private function verifySqliteCopy(string $path, string $name): array
    {
        File::ensureDirectoryExists($this->drillDirectory());

        $scratch = $this->drillDirectory().DIRECTORY_SEPARATOR.'drill-'.uniqid().'.sqlite';
        File::copy($path, $scratch);

        $pdo = null;

        try {
            $pdo = new PDO('sqlite:'.$scratch, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $integrity = $pdo->query('PRAGMA integrity_check')->fetchAll(PDO::FETCH_COLUMN);

            if (count($integrity) !== 1 || strtolower(trim((string) $integrity[0])) !== 'ok') {
                $detail = is_string($integrity[0] ?? null) ? (string) $integrity[0] : 'integrity check failed';

                return $this->fail($name, 'sqlite', "The restored copy failed its integrity check ({$detail}).");
            }

            $tables = $pdo->query(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
            )->fetchAll(PDO::FETCH_COLUMN);

            if ($tables === []) {
                return $this->fail($name, 'sqlite', 'The restored copy contains no tables.');
            }

            $rows = 0;

            foreach ($tables as $table) {
                // Identifiers come from the backup itself, so quote them the
                // paranoid way before interpolating.
                $quoted = '"'.str_replace('"', '""', (string) $table).'"';
                $rows += (int) $pdo->query("SELECT COUNT(*) FROM {$quoted}")->fetchColumn();
            }

            if ($rows === 0) {
                return $this->fail($name, 'sqlite', 'The restored copy has tables but holds zero rows.');
            }

            $this->markVerified($name, 'sqlite', count($tables), $rows);

            return [
                'ok' => true,
                'skipped' => false,
                'file' => $name,
                'format' => 'sqlite',
                'tables' => count($tables),
                'rows' => $rows,
                'message' => "Drill passed: {$name} restored into a throwaway database (".count($tables).' tables, '.$rows.' rows) with no errors. The scratch file was removed; the live database was untouched.',
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return $this->fail($name, 'sqlite', 'The backup could not be restored: '.$exception->getMessage());
        } finally {
            // Null the handle BEFORE unlinking: on Windows an open SQLite
            // handle blocks the delete and the scratch file would survive.
            $pdo = null;
            $this->removeScratch($scratch);
        }
    }

    /**
     * Structural verification for a MySQL/MariaDB dump.
     *
     * The dump speaks MySQL DDL (backticks, ENGINE=, …) that SQLite cannot
     * execute, and spinning up a throwaway MySQL server is outside what a
     * field drill can assume — so instead of executing it, the drill scans
     * the script line by line (never loading the whole file) and requires the
     * markers a complete BackupService dump always carries: its header, at
     * least one CREATE TABLE, at least one INSERT, and the closing
     * FOREIGN_KEY_CHECKS = 1 footer that is written last. A truncated dump
     * fails the footer check.
     *
     * @return array{ok: bool, skipped: bool, file: ?string, format: string, tables: int, rows: int, message: string, error: ?string}
     */
    private function verifyMysqlDump(string $path, string $name): array
    {
        File::ensureDirectoryExists($this->drillDirectory());

        $scratch = $this->drillDirectory().DIRECTORY_SEPARATOR.'drill-'.uniqid().'.sql';

        try {
            // The dump is read through a scratch copy so the original is
            // never held open by the drill, and so the "scratch always
            // removed" invariant holds for this format too.
            File::copy($path, $scratch);

            $scan = $this->scanMysqlDump($scratch);

            if (! $scan['header']) {
                return $this->fail($name, 'mysql-dump', 'The dump is missing the backup header, so it was not produced by this application.');
            }

            if ($scan['tables'] === 0) {
                return $this->fail($name, 'mysql-dump', 'The dump contains no CREATE TABLE statements.');
            }

            if ($scan['inserts'] === 0) {
                return $this->fail($name, 'mysql-dump', 'The dump contains schema but no INSERT statements.');
            }

            if (! $scan['footer']) {
                return $this->fail($name, 'mysql-dump', 'The dump is missing its closing marker — it looks truncated.');
            }

            $this->markVerified($name, 'mysql-dump', $scan['tables'], $scan['inserts']);

            return [
                'ok' => true,
                'skipped' => false,
                'file' => $name,
                'format' => 'mysql-dump',
                'tables' => $scan['tables'],
                'rows' => $scan['inserts'],
                'message' => "Drill passed: {$name} is a complete MySQL dump ({$scan['tables']} tables, {$scan['inserts']} INSERT statements, closing marker present). Verified structurally — replaying it needs a MySQL server, so no live database was touched.",
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return $this->fail($name, 'mysql-dump', 'The dump could not be read: '.$exception->getMessage());
        } finally {
            $this->removeScratch($scratch);
        }
    }

    /**
     * @return array{header: bool, tables: int, inserts: int, footer: bool}
     */
    private function scanMysqlDump(string $path): array
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new \RuntimeException('The scratch copy could not be opened for reading.');
        }

        $header = false;
        $footer = false;
        $tables = 0;
        $inserts = 0;

        try {
            // Header markers are always written first, so the first read
            // settles it without scanning the whole file.
            $head = (string) fread($handle, 4096);
            $header = str_contains($head, 'Barangay Management System - database backup')
                || str_contains($head, 'SET FOREIGN_KEY_CHECKS = 0;');

            // Line-prefix scan, one line at a time, so arbitrarily large
            // dumps never sit in memory whole and no keyword is ever split
            // across a read boundary. This matches the producer exactly:
            // BackupService starts every CREATE TABLE and every INSERT on
            // its own line; continuation lines start with whitespace or "(".
            rewind($handle);

            while (($line = fgets($handle)) !== false) {
                if (str_starts_with($line, 'CREATE TABLE ')) {
                    $tables++;
                } elseif (str_starts_with($line, 'INSERT INTO ')) {
                    $inserts++;
                }
            }

            // The footer is written last, so a truncated dump fails here.
            // Small dumps are shorter than the tail window — read those whole.
            if (fstat($handle)['size'] < 2048) {
                rewind($handle);
                $tail = (string) stream_get_contents($handle);
            } else {
                fseek($handle, -2048, SEEK_END);
                $tail = (string) stream_get_contents($handle);
            }

            $footer = str_contains($tail, 'SET FOREIGN_KEY_CHECKS = 1;');
        } finally {
            fclose($handle);
        }

        return ['header' => $header, 'tables' => $tables, 'inserts' => $inserts, 'footer' => $footer];
    }

    private function isSqliteFile(string $path): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        try {
            $magic = fread($handle, 16);
        } finally {
            fclose($handle);
        }

        return $magic === "SQLite format 3\0";
    }

    private function looksLikeMysqlDump(string $path): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        try {
            $head = (string) fread($handle, 4096);
        } finally {
            fclose($handle);
        }

        return str_contains($head, 'Barangay Management System - database backup')
            || str_contains($head, 'SET FOREIGN_KEY_CHECKS')
            || str_contains($head, 'CREATE TABLE ')
            || str_contains($head, 'INSERT INTO ');
    }

    private function markVerified(string $name, string $format, int $tables, int $rows): void
    {
        try {
            Cache::put(self::CACHE_KEY, now()->timestamp);
            Cache::put(self::CACHE_FILE_KEY, $name);
            Cache::put(self::CACHE_DETAIL_KEY, [
                'file' => $name,
                'format' => $format,
                'tables' => $tables,
                'rows' => $rows,
            ]);
        } catch (Throwable) {
            // The drill already passed — a cache outage must not fail it.
        }

        $this->audit('system.backup_drill_verified', [
            'drill' => true,
            'file' => $name,
            'format' => $format,
            'tables' => $tables,
            'rows' => $rows,
        ]);
    }

    /**
     * @return array{ok: bool, skipped: bool, file: ?string, format: string, tables: int, rows: int, message: string, error: ?string}
     */
    private function fail(string $name, string $format, string $error): array
    {
        $this->audit('system.backup_drill_failed', [
            'drill' => true,
            'file' => $name,
            'format' => $format,
            'error' => mb_substr($error, 0, 500),
        ]);

        // A failed drill must never refresh the reminder: the maintenance
        // page keeps showing the last PASSING drill (or "Never verified").
        return $this->result(false, false, $name, $format, $error);
    }

    /**
     * @return array{ok: bool, skipped: bool, file: ?string, format: string, tables: int, rows: int, message: string, error: ?string}
     */
    private function result(bool $ok, bool $skipped, ?string $file, string $format, string $message): array
    {
        return [
            'ok' => $ok,
            'skipped' => $skipped,
            'file' => $file,
            'format' => $format,
            'tables' => 0,
            'rows' => 0,
            'message' => $message,
            'error' => $ok ? null : $message,
        ];
    }

    private function audit(string $event, array $properties): void
    {
        AuditLog::record($event, null, null, 'console', 'backup:verify', $properties);
    }

    private function removeScratch(string $scratch): void
    {
        try {
            File::delete($scratch);

            // A crashed SQLite open can leave -journal/-wal/-shm sidecars
            // next to the scratch file; they belong to the drill, not the
            // operator, so they go too.
            foreach (['-journal', '-wal', '-shm'] as $suffix) {
                File::delete($scratch.$suffix);
            }
        } catch (Throwable) {
            // Best effort: the drill result is already decided.
        }
    }
}
