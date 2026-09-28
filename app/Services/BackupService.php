<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class BackupService
{
    /**
     * SQLite hands us a single file we can copy byte for byte. MySQL and
     * MariaDB have no such file, so they get an importable SQL script.
     */
    private const SQLITE_EXTENSION = 'sqlite';

    private const SQL_EXTENSION = 'sql';

    /**
     * The only filenames this service ever creates, and therefore the only
     * ones it will list, stream or delete. Sharing one pattern between
     * `all()` and `path()` means the maintenance page can never advertise a
     * file that the download route would refuse to serve.
     */
    private const FILENAME_PATTERN = '/^barangay-\d{8}-\d{6}(?:-\d+)?\.(?:sqlite|sql)$/';

    /**
     * Rows per INSERT statement: small enough that each statement stays
     * readable and memory stays flat on a large table, large enough to keep
     * the statement count sensible.
     */
    private const INSERT_CHUNK = 250;

    public function __construct(
        private readonly ?string $directory = null,
        private readonly int $keep = 10,
    ) {}

    public function directory(): string
    {
        return $this->directory ?? storage_path('app/private/backups');
    }

    /**
     * Create a private database backup. The file is never placed under public/.
     */
    public function create(): array
    {
        $driver = (string) config('database.default');

        if (! in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)) {
            throw new RuntimeException(sprintf(
                'Automatic backup creation is not supported for the "%s" database driver.',
                $driver
            ));
        }

        File::ensureDirectoryExists($this->directory());

        $target = $this->availablePath(
            $driver === 'sqlite' ? self::SQLITE_EXTENSION : self::SQL_EXTENSION
        );

        try {
            $written = $driver === 'sqlite'
                ? $this->snapshot($target)
                : $this->dump($target);
        } catch (Throwable $exception) {
            // A half-written file would still be listed and offered for
            // download, so clear it away before letting the failure through.
            File::delete($target);
            throw $exception;
        }

        if (! $written) {
            File::delete($target);
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
     * Write an importable SQL script for MySQL and MariaDB.
     *
     * Every row is read inside one transaction, so the script describes the
     * database at a single moment. Without that, a backup taken while an
     * office worker saves a record could hold the record in one table but not
     * its related row in another. InnoDB's default REPEATABLE READ level
     * establishes the snapshot at the first read.
     *
     * The script is streamed to disk as it is produced rather than assembled
     * in memory, so the database never has to fit inside PHP's heap.
     */
    private function dump(string $target): bool
    {
        $handle = @fopen($target, 'wb');

        if ($handle === false) {
            return false;
        }

        try {
            $this->write($handle, $this->dumpHeader(basename($target)));

            DB::beginTransaction();

            try {
                foreach ($this->tables() as $table) {
                    $this->writeTable($handle, $table);
                }

                DB::commit();
            } catch (Throwable $exception) {
                DB::rollBack();
                throw $exception;
            }

            $this->write($handle, "\nSET FOREIGN_KEY_CHECKS = 1;\n");
        } finally {
            fclose($handle);
        }

        return true;
    }

    private function dumpHeader(string $filename): string
    {
        return implode("\n", [
            '-- Barangay Management System - database backup',
            '-- Generated: '.now()->toDateTimeString(),
            '-- Driver: '.config('database.default'),
            '--',
            '-- Restore with:',
            '--   mysql -u <user> -p <database> < '.$filename,
            '--',
            'SET NAMES utf8mb4;',
            "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';",
            'SET FOREIGN_KEY_CHECKS = 0;',
            '',
        ]);
    }

    /**
     * Base tables only: a view has no `SHOW CREATE TABLE` output worth
     * writing, and this schema does not use any.
     *
     * @return list<string>
     */
    private function tables(): array
    {
        $rows = DB::select(
            'SELECT TABLE_NAME AS name
               FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_TYPE = ?
              ORDER BY TABLE_NAME',
            ['BASE TABLE']
        );

        return array_map(fn (object $row): string => (string) $row->name, $rows);
    }

    /**
     * @param  resource  $handle
     */
    private function writeTable($handle, string $table): void
    {
        $identifier = $this->identifier($table);

        $this->write($handle, "\nDROP TABLE IF EXISTS {$identifier};\n");
        $this->write($handle, $this->createTableStatement($table).";\n");

        $offset = 0;

        while (true) {
            $rows = DB::select(
                "SELECT * FROM {$identifier} LIMIT ".self::INSERT_CHUNK.' OFFSET '.$offset
            );

            if ($rows === []) {
                break;
            }

            $this->write($handle, $this->insertStatement($table, $rows));
            $offset += self::INSERT_CHUNK;

            if (count($rows) < self::INSERT_CHUNK) {
                break;
            }
        }
    }

    private function createTableStatement(string $table): string
    {
        $row = (array) (DB::selectOne('SHOW CREATE TABLE '.$this->identifier($table)) ?? []);

        // The DDL is the second column; read by position because MariaDB
        // labels it differently from MySQL.
        $statement = array_values($row)[1] ?? null;

        if (! is_string($statement) || $statement === '') {
            throw new RuntimeException(sprintf(
                'The schema for table "%s" could not be read for the backup.',
                $table
            ));
        }

        return $statement;
    }

    private function insertStatement(string $table, array $rows): string
    {
        $columns = array_keys((array) $rows[0]);
        $heading = array_map(fn (string $column): string => $this->identifier($column), $columns);

        $tuples = array_map(function (object $row) use ($columns): string {
            $values = array_map(
                fn (string $column): string => $this->quoteValue(((array) $row)[$column] ?? null),
                $columns
            );

            return '('.implode(', ', $values).')';
        }, $rows);

        return sprintf(
            "INSERT INTO %s (%s) VALUES\n%s;\n",
            $this->identifier($table),
            implode(', ', $heading),
            implode(",\n", $tuples)
        );
    }

    /**
     * Quote a table or column name. Identifiers come from our own schema, but
     * refusing anything outside [A-Za-z0-9_] means a corrupt or hostile name
     * can never break out of the backticks.
     */
    private function identifier(string $name): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
            throw new RuntimeException(sprintf(
                'Cannot back up: the identifier "%s" contains characters this backup writer does not handle.',
                $name
            ));
        }

        return '`'.$name.'`';
    }

    /**
     * Escape one value for the script.
     *
     * Strings go through PDO's own escaper, which covers quotes, backslashes,
     * newlines and NUL bytes. Numbers are written bare so that an id of 0
     * stays 0 rather than becoming the next auto-increment value.
     */
    private function quoteValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            // var_export() uses serialize_precision (-1), so a float comes
            // back as the shortest string that round-trips exactly.
            return var_export($value, true);
        }

        $quoted = DB::getPdo()->quote((string) $value);

        if ($quoted === false) {
            throw new RuntimeException('A database value could not be escaped for the backup.');
        }

        return $quoted;
    }

    /**
     * Write the whole buffer, tolerating partial writes so a backup that ran
     * out of disk fails loudly instead of being saved short.
     *
     * @param  resource  $handle
     */
    private function write($handle, string $data): void
    {
        $length = strlen($data);
        $written = 0;

        while ($written < $length) {
            $bytes = fwrite($handle, substr($data, $written));

            if ($bytes === false || $bytes === 0) {
                throw new RuntimeException('The backup file could not be written to disk.');
            }

            $written += $bytes;
        }
    }

    /**
     * A path that does not exist yet. Two backups inside the same second are
     * suffixed rather than overwriting one another.
     */
    private function availablePath(string $extension): string
    {
        $base = 'barangay-'.now()->format('Ymd-His');
        $target = $this->directory().DIRECTORY_SEPARATOR.$base.'.'.$extension;
        $suffix = 1;

        while (File::exists($target)) {
            $target = $this->directory().DIRECTORY_SEPARATOR.$base.'-'.$suffix.'.'.$extension;
            $suffix++;
        }

        return $target;
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
            ->filter(fn ($file) => preg_match(self::FILENAME_PATTERN, $file->getFilename()) === 1)
            ->map(fn ($file) => $this->describe($file->getPathname()))
            ->sortByDesc('created_at')
            ->values()
            ->all();
    }

    public function path(string $name): string
    {
        $name = basename($name);

        if (preg_match(self::FILENAME_PATTERN, $name) !== 1) {
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
