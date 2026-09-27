<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class BackupService
{
    public function __construct(
        private readonly ?string $directory = null,
        private readonly int $keep = 10,
    ) {}

    public function directory(): string
    {
        return $this->directory ?? storage_path('app/private/backups');
    }

    /**
     * Create a private SQLite backup. The file is never placed under public/.
     */
    public function create(): array
    {
        if (config('database.default') !== 'sqlite') {
            throw new RuntimeException('Automatic backup creation is currently enabled for SQLite only.');
        }

        $source = config('database.connections.sqlite.database');
        if (! is_string($source) || $source === ':memory:' || ! File::exists($source)) {
            throw new RuntimeException('The SQLite database file is not available for backup.');
        }

        File::ensureDirectoryExists($this->directory());
        $name = 'barangay-'.now()->format('Ymd-His').'.sqlite';
        $target = $this->directory().DIRECTORY_SEPARATOR.$name;
        $suffix = 1;

        while (File::exists($target)) {
            $name = 'barangay-'.now()->format('Ymd-His').'-'.$suffix.'.sqlite';
            $target = $this->directory().DIRECTORY_SEPARATOR.$name;
            $suffix++;
        }

        if (! $this->snapshot($target)) {
            throw new RuntimeException('The database backup could not be created.');
        }

        $this->pruneOldBackups();

        return $this->describe($target);
    }

    /**
     * Write a consistent copy of the live database to $target.
     *
     * A plain file copy of a SQLite database that is being written to can
     * capture a torn snapshot. `VACUUM INTO` takes a read lock, produces a
     * transactionally consistent file, and never modifies the source. When the
     * runtime cannot use it (an older SQLite build) the service falls back to a
     * copy so that a backup still exists.
     */
    private function snapshot(string $target): bool
    {
        $escaped = str_replace("'", "''", $target);

        try {
            DB::statement("VACUUM INTO '{$escaped}'");

            if (File::isFile($target) && File::size($target) > 0) {
                return true;
            }
        } catch (\Throwable) {
            // Fall through to the copy below.
        }

        return File::copy((string) config('database.connections.sqlite.database'), $target);
    }

    /**
     * @return array<int, array{name: string, size: int, created_at: int}>
     */
    public function all(): array
    {
        if (! File::isDirectory($this->directory())) {
            return [];
        }

        return collect(File::files($this->directory()))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.sqlite'))
            ->map(fn ($file) => $this->describe($file->getPathname()))
            ->sortByDesc('created_at')
            ->values()
            ->all();
    }

    public function path(string $name): string
    {
        $name = basename($name);

        if (! preg_match('/^barangay-\d{8}-\d{6}(?:-\d+)?\.sqlite$/', $name)) {
            abort(404);
        }

        $path = $this->directory().DIRECTORY_SEPARATOR.$name;
        abort_unless(File::isFile($path), 404);

        return $path;
    }

    public function delete(string $name): void
    {
        File::delete($this->path($name));
    }

    private function pruneOldBackups(): void
    {
        $keep = max(1, $this->keep);
        collect($this->all())
            ->slice($keep)
            ->each(fn (array $backup) => File::delete($this->path($backup['name'])));
    }

    /**
     * @return array{name: string, size: int, created_at: int}
     */
    private function describe(string $path): array
    {
        return [
            'name' => basename($path),
            'size' => File::size($path),
            'created_at' => File::lastModified($path),
        ];
    }
}
