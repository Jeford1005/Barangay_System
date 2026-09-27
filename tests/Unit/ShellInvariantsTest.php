<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Guards for the application shell that no other test would catch.
 *
 * These are cheap assertions about invariants that are easy to break with an
 * unrelated edit and expensive to notice in production: a print stylesheet that
 * drops real columns, a responsive script that leaves a stale inline offset on
 * phones, and an icon name that renders nothing.
 */
class ShellInvariantsTest extends TestCase
{
    /**
     * @return array<int, string>
     */
    private function bladeFiles(): array
    {
        $files = glob(resource_path('views/**/*.blade.php')) ?: [];

        // glob() does not recurse in PHP, so walk instead.
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        $paths = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $paths[] = $file->getPathname();
            }
        }

        return $paths ?: $files;
    }

    public function test_print_stylesheet_does_not_hide_columns_by_position(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        // `:last-child` on table cells assumes the final column is always the
        // action buttons. On the approvals, corrections, and audit tables it is
        // a timestamp or a review outcome, so print silently dropped real data.
        $this->assertDoesNotMatchRegularExpression(
            '/(thead|tbody)\s+(th|td):last-child[^{]*\{[^}]*display:\s*none/i',
            $css,
            'Print CSS must not hide table columns by position; use the no-print class on the action cells instead.',
        );
    }

    public function test_action_columns_carry_the_no_print_class(): void
    {
        $missing = [];

        foreach ($this->bladeFiles() as $path) {
            $contents = (string) file_get_contents($path);
            $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);

            // Any header whose text is literally "Actions" must be hidden when
            // printed, otherwise removing the blanket `:last-child` rule leaves
            // an empty column on the paper output.
            preg_match_all('/<th[^>]*>[^<]*Actions[^<]*<\/th>/i', $contents, $headers);

            foreach ($headers[0] as $tag) {
                if (! str_contains($tag, 'no-print')) {
                    $missing[] = $relative.' (th)';
                }
            }

            // The matching body cells: a right-aligned cell that contains only
            // links or a form is an action cell.
            preg_match_all('/<td class="([^"]*text-right[^"]*)">(.*?)<\/td>/is', $contents, $cells, PREG_SET_ORDER);

            foreach ($cells as $cell) {
                if (! str_contains($cell[2], '<a ') && ! str_contains($cell[2], '<form')) {
                    continue;
                }

                if (! str_contains($cell[1], 'no-print')) {
                    $missing[] = $relative.' (td)';
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($missing)),
            'Action columns must carry the no-print class: '.implode(', ', array_unique($missing))
        );
    }

    public function test_every_icon_name_used_in_a_view_resolves(): void
    {
        $registry = (string) file_get_contents(resource_path('views/components/icon.blade.php'));

        preg_match_all("/'([a-z0-9-]+)'\s*=>/i", $registry, $defined);
        preg_match_all("/'([a-z0-9]+)'\s*=>\s*'[a-z0-9-]+',/i", $registry, $aliases);

        $known = array_merge($defined[1], $aliases[1]);

        $unknown = [];

        foreach ($this->bladeFiles() as $path) {
            preg_match_all('/<x-icon\s+name="([a-z0-9-]+)"/i', (string) file_get_contents($path), $used);

            foreach (array_unique($used[1]) as $name) {
                if (! in_array($name, $known, true)) {
                    $unknown[$name] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
                }
            }
        }

        $this->assertSame(
            [],
            $unknown,
            'Icons used in views but missing from the registry: '.implode(', ', array_keys($unknown))
        );
    }

    public function test_the_app_reports_a_local_timezone_by_default(): void
    {
        // UTC would shift every report boundary, "today" check, printed issue
        // stamp, and CSV timestamp by eight hours for a Philippine barangay.
        $this->assertSame('Asia/Manila', config('app.timezone'));
    }

    public function test_sqlite_is_configured_for_concurrent_office_use(): void
    {
        $connection = config('database.connections.sqlite');

        // `lockForUpdate()` compiles to nothing on SQLite, so the only defence
        // against concurrent writers is a busy timeout plus WAL.
        $this->assertNotNull($connection['busy_timeout']);
        $this->assertSame('WAL', $connection['journal_mode']);
    }
}
