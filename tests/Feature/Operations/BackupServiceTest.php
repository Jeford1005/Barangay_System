<?php

namespace Tests\Feature\Operations;

use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PDO;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;
use Throwable;

class BackupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sql_backups_are_listed_and_served(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'barangay-backups-'.uniqid();
        File::ensureDirectoryExists($directory);
        File::put($directory.DIRECTORY_SEPARATOR.'barangay-20260101-120000.sql', '-- backup');

        try {
            $service = new BackupService($directory);

            $this->assertCount(1, $service->all());
            $this->assertSame(
                $directory.DIRECTORY_SEPARATOR.'barangay-20260101-120000.sql',
                $service->path('barangay-20260101-120000.sql')
            );
            $this->assertSame('-- backup', File::get($service->path('barangay-20260101-120000.sql')));
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_pruning_covers_both_backup_formats(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'barangay-source-');
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'barangay-backups-'.uniqid();
        File::put($source, 'sqlite-test');
        File::ensureDirectoryExists($directory);

        // One of each supported format, both older than the backup we are
        // about to create. With keep = 2 the oldest must go - whichever
        // format it happens to be in.
        File::put($directory.DIRECTORY_SEPARATOR.'barangay-20200101-000000.sql', 'old');
        File::put($directory.DIRECTORY_SEPARATOR.'barangay-20200102-000000.sqlite', 'older');
        touch($directory.DIRECTORY_SEPARATOR.'barangay-20200101-000000.sql', now()->subDays(2)->timestamp);
        touch($directory.DIRECTORY_SEPARATOR.'barangay-20200102-000000.sqlite', now()->subDay()->timestamp);

        $oldDefault = config('database.default');
        $oldDatabase = config('database.connections.sqlite.database');
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $source]);

        try {
            (new BackupService($directory, 2))->create();

            $this->assertFileDoesNotExist($directory.DIRECTORY_SEPARATOR.'barangay-20200101-000000.sql');
            $this->assertFileExists($directory.DIRECTORY_SEPARATOR.'barangay-20200102-000000.sqlite');
            $this->assertCount(2, (new BackupService($directory))->all());
        } finally {
            config(['database.default' => $oldDefault, 'database.connections.sqlite.database' => $oldDatabase]);
            File::delete($source);
            File::deleteDirectory($directory);
        }
    }

    public function test_a_file_outside_the_backup_pattern_is_rejected(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'barangay-backups-'.uniqid();
        File::ensureDirectoryExists($directory);
        File::put($directory.DIRECTORY_SEPARATOR.'barangay-20260101-120000.txt', 'not a backup');

        try {
            $service = new BackupService($directory);

            // A file we will neither list nor serve, so the maintenance table
            // can never offer a download that 404s.
            $this->assertSame([], $service->all());

            $this->expectException(NotFoundHttpException::class);
            $service->path('barangay-20260101-120000.txt');
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_an_unsupported_driver_fails_clearly_and_writes_nothing(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'barangay-backups-'.uniqid();
        File::ensureDirectoryExists($directory);

        $oldDefault = config('database.default');
        config(['database.default' => 'pgsql']);

        try {
            $service = new BackupService($directory);

            try {
                $service->create();
                $this->fail('The unsupported driver should have been rejected.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('not supported', $exception->getMessage());
                $this->assertStringContainsString('pgsql', $exception->getMessage());
            }

            $this->assertSame([], File::files($directory));
            $this->assertSame([], $service->all());
        } finally {
            config(['database.default' => $oldDefault]);
            File::deleteDirectory($directory);
        }
    }

    /**
     * The real proof that the MySQL writer works: take a backup of a scratch
     * database, restore it into an empty one, and require the two to match
     * row for row. Anything the writer escapes wrongly shows up here as a
     * difference rather than as silently corrupted data in production.
     */
    public function test_a_mysql_backup_restores_into_an_empty_database(): void
    {
        $config = (array) config('database.connections.mysql');
        $source = 'barangay_backup_source_test';
        $target = 'barangay_backup_restore_test';

        try {
            $control = $this->connect($config);
        } catch (Throwable $exception) {
            $this->markTestSkipped('MySQL is not reachable: '.$exception->getMessage());
        }

        try {
            $control->exec("CREATE DATABASE IF NOT EXISTS `$source` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $control->exec("CREATE DATABASE IF NOT EXISTS `$target` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (Throwable $exception) {
            $this->markTestSkipped('The MySQL user cannot create databases: '.$exception->getMessage());
        }

        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'barangay-backups-'.uniqid();
        $oldDefault = config('database.default');
        $oldDatabase = $config['database'] ?? null;

        try {
            $control->exec('DROP TABLE IF EXISTS `'.$source.'`.roundtrip');
            $control->exec('DROP TABLE IF EXISTS `'.$target.'`.roundtrip');
            $control->exec('SET SESSION sql_mode = \'NO_AUTO_VALUE_ON_ZERO\'');

            $control->exec("CREATE TABLE `$source`.roundtrip (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                label VARCHAR(255) NULL,
                note TEXT NULL,
                amount DECIMAL(10,2) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $expected = [
                // id = 0 must survive: without NO_AUTO_VALUE_ON_ZERO the
                // restore would turn it into the next auto-increment value.
                ['id' => 0, 'label' => null, 'note' => null, 'amount' => null],
                ['id' => 1, 'label' => 'O\'Brien said "hi"', 'note' => "line one\nline two", 'amount' => '1234.50'],
                ['id' => 2, 'label' => 'back\\slash', 'note' => 'semi; colon -- not a comment', 'amount' => '0.00'],
                ['id' => 3, 'label' => '👮 barangay café ñ 中文', 'note' => '', 'amount' => '-99.99'],
                ['id' => 4, 'label' => "nul\0byte", 'note' => "ctrl-z \x1a", 'amount' => '0.10'],
            ];

            $insert = $control->prepare(
                "INSERT INTO `$source`.roundtrip (id, label, note, amount) VALUES (?, ?, ?, ?)"
            );

            foreach ($expected as $row) {
                $insert->execute([$row['id'], $row['label'], $row['note'], $row['amount']]);
            }

            File::ensureDirectoryExists($directory);
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.database' => $source,
            ]);

            $backup = (new BackupService($directory))->create();
            $path = $directory.DIRECTORY_SEPARATOR.$backup['name'];

            $contents = File::get($path);
            $this->assertStringEndsWith('.sql', $backup['name']);
            $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS = 0;', $contents);
            $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS = 1;', $contents);
            $this->assertStringContainsString('DROP TABLE IF EXISTS `roundtrip`;', $contents);
            $this->assertStringContainsString('CREATE TABLE `roundtrip`', $contents);
            $this->assertStringContainsString('INSERT INTO `roundtrip`', $contents);

            $control->exec("DROP DATABASE `$target`");
            $control->exec("CREATE DATABASE `$target` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $control->exec("USE `$target`");
            $control->exec($contents);

            $sourceRows = $control
                ->query("SELECT id, label, note, amount FROM `$source`.roundtrip ORDER BY id")
                ->fetchAll(PDO::FETCH_ASSOC);
            $targetRows = $control
                ->query("SELECT id, label, note, amount FROM `$target`.roundtrip ORDER BY id")
                ->fetchAll(PDO::FETCH_ASSOC);

            $this->assertNotSame([], $sourceRows);
            $this->assertSame($expected, $sourceRows, 'The source database should hold exactly what was inserted.');
            $this->assertSame($sourceRows, $targetRows, 'The restored database must match the source exactly.');
        } finally {
            config(['database.default' => $oldDefault, 'database.connections.mysql.database' => $oldDatabase]);
            File::deleteDirectory($directory);

            try {
                $control->exec("DROP DATABASE IF EXISTS `$source`");
                $control->exec("DROP DATABASE IF EXISTS `$target`");
            } catch (Throwable) {
                // Leaving a scratch database behind must not fail the test.
            }
        }
    }

    private function connect(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;charset=utf8mb4',
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 3306
        );

        return new PDO($dsn, $config['username'] ?? '', $config['password'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        ]);
    }
}
