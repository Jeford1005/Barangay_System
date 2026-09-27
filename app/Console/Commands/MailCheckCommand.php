<?php

namespace App\Console\Commands;

use App\Services\MailHealthChecker;
use Illuminate\Console\Command;

class MailCheckCommand extends Command
{
    protected $signature = 'mail:check
                            {--send : Also send a test email to the configured from-address}
                            {--to= : Send the test email to this address instead}';

    protected $description = 'Verify mail configuration for password-reset emails (config audit, connectivity, optional test send)';

    public function handle(MailHealthChecker $checker): int
    {
        $this->info('Checking mail configuration…');
        $this->newLine();

        $sendTest = $this->option('send') || $this->option('to') !== null;
        $report = $checker->run($sendTest, $this->option('to'));

        foreach ($report['checks'] as $check) {
            $icon = match ($check['status']) {
                'pass' => '<fg=green;options=bold>✔</>',
                'warn' => '<fg=yellow;options=bold>!</>',
                default => '<fg=red;options=bold>✘</>',
            };

            $this->line("  {$icon} <options=bold>{$check['label']}:</> {$check['detail']}");

            if ($check['hint'] ?? null) {
                $this->line("      <fg=yellow>↳ {$check['hint']}</>");
            }
        }

        $this->newLine();

        if ($report['ready']) {
            $this->info('✅ Mail is ready — password-reset emails can be sent.');

            return self::SUCCESS;
        }

        $this->warn('⚠  Mail is not ready. Fix the items above, then run `php artisan mail:check --send` to verify end-to-end.');
        $this->line('   Full Gmail setup steps: see .env.example or the README (Mail / Gmail SMTP section).');

        return self::FAILURE;
    }
}
