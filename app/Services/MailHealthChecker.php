<?php

namespace App\Services;

use App\Mail\MailSetupTestMail;
use Illuminate\Support\Facades\Mail;

class MailHealthChecker
{
    /**
     * Run every check. Returns a structured report for both the
     * artisan command and the admin dashboard page.
     *
     * @param  bool  $sendTest  Whether to actually send a test email.
     * @param  string|null  $testRecipient  Where the test email goes.
     */
    public function run(bool $sendTest = false, ?string $testRecipient = null): array
    {
        $checks = [];

        $checks[] = $this->checkMailer();
        $checks = array_merge($checks, $this->checkSmtpConfig());
        $checks[] = $this->checkFromAddress();

        $mailer = config('mail.default');

        if ($mailer === 'smtp' && $this->allPassed($checks)) {
            $checks[] = $this->checkConnectivity();
        }

        if ($sendTest) {
            $checks[] = $this->sendTest($testRecipient);
        }

        return [
            'checks' => $checks,
            'mailer' => $mailer,
            'ready' => $this->allPassed($checks),
        ];
    }

    /**
     * @param  array<int, array{label:string, status:string, hint:?string}>  $checks
     */
    private function allPassed(array $checks): bool
    {
        // Only an all-green checklist counts as ready — warnings mean the
        // setup is questionable (log driver, non-standard port, etc.).
        return ! in_array('fail', array_column($checks, 'status'), true)
            && ! in_array('warn', array_column($checks, 'status'), true);
    }

    /**
     * @return array{label:string, status:string, hint:?string}
     */
    private function checkMailer(): array
    {
        $mailer = config('mail.default');

        if ($mailer === 'smtp') {
            return [
                'label' => 'Mailer driver',
                'status' => 'pass',
                'detail' => 'smtp — real emails will be sent',
                'hint' => null,
            ];
        }

        if ($mailer === 'log') {
            return [
                'label' => 'Mailer driver',
                'status' => 'warn',
                'detail' => 'log — emails are written to storage/logs/laravel.log, not delivered',
                'hint' => 'Set MAIL_MAILER=smtp in .env to send real password-reset emails.',
            ];
        }

        return [
            'label' => 'Mailer driver',
            'status' => 'pass',
            'detail' => $mailer,
            'hint' => null,
        ];
    }

    /**
     * @return array<int, array{label:string, status:string, detail:string, hint:?string}>
     */
    private function checkSmtpConfig(): array
    {
        if (config('mail.default') !== 'smtp') {
            return [];
        }

        $checks = [];

        $host = config('mail.mailers.smtp.host');
        $checks[] = [
            'label' => 'SMTP host',
            'status' => $host === 'smtp.gmail.com' ? 'pass' : 'warn',
            'detail' => $host,
            'hint' => $host === 'smtp.gmail.com' ? null : 'For Gmail, set MAIL_HOST=smtp.gmail.com.',
        ];

        $port = (string) config('mail.mailers.smtp.port');
        $checks[] = [
            'label' => 'SMTP port',
            'status' => in_array($port, ['587', '465'], true) ? 'pass' : 'warn',
            'detail' => $port.($port === '587' ? ' (STARTTLS)' : ($port === '465' ? ' (implicit TLS)' : '')),
            'hint' => in_array($port, ['587', '465'], true) ? null : 'Gmail uses port 587 (STARTTLS) or 465 (implicit TLS).',
        ];

        $username = config('mail.mailers.smtp.username');
        $checks[] = [
            'label' => 'SMTP username',
            'status' => filled($username) ? 'pass' : 'fail',
            'detail' => filled($username) ? $username : '(empty)',
            'hint' => filled($username) ? null : 'Set MAIL_USERNAME to your Gmail address.',
        ];

        $password = config('mail.mailers.smtp.password');
        $looksLikeAppPassword = is_string($password)
            && preg_match('/^[A-Za-z0-9]{16}$/', str_replace(' ', '', $password)) === 1;
        $checks[] = [
            'label' => 'SMTP password (App Password)',
            'status' => $password ? ($looksLikeAppPassword ? 'pass' : 'warn') : 'fail',
            'detail' => $password ? str_repeat('•', 16) : '(empty)',
            'hint' => ! $password
                ? 'Set MAIL_PASSWORD to a 16-character Google App Password (myaccount.google.com/apppasswords).'
                : ($looksLikeAppPassword ? null : 'This does not look like a 16-character App Password — Gmail rejects normal account passwords.'),
        ];

        return $checks;
    }

    /**
     * @return array{label:string, status:string, detail:string, hint:?string}
     */
    private function checkFromAddress(): array
    {
        $from = (string) config('mail.from.address');
        $username = (string) config('mail.mailers.smtp.username');
        $isGmail = config('mail.default') === 'smtp'
            && str_ends_with(strtolower($username), '@gmail.com');

        $mismatch = $isGmail
            && strtolower($from) !== strtolower($username);

        return [
            'label' => 'From address',
            'status' => $mismatch ? 'fail' : (filled($from) ? 'pass' : 'fail'),
            'detail' => $from ?: '(empty)',
            'hint' => $mismatch
                ? 'MAIL_FROM_ADDRESS must be the same Gmail account as MAIL_USERNAME, or Google rejects the message.'
                : (filled($from) ? null : 'Set MAIL_FROM_ADDRESS in .env.'),
        ];
    }

    /**
     * Open a socket to the SMTP host to catch network/firewall problems
     * before a real reset email is attempted.
     *
     * @return array{label:string, status:string, detail:string, hint:?string}
     */
    private function checkConnectivity(): array
    {
        $host = (string) config('mail.mailers.smtp.host');
        $port = (int) config('mail.mailers.smtp.port');

        $connected = @fsockopen($host, $port, $errno, $errstr, 5);

        if ($connected === false) {
            return [
                'label' => "SMTP connection ({$host}:{$port})",
                'status' => 'fail',
                'detail' => "could not connect — {$errstr} ({$errno})",
                'hint' => 'Check your internet connection or firewall. Some networks block outbound SMTP.',
            ];
        }

        fclose($connected);

        return [
            'label' => "SMTP connection ({$host}:{$port})",
            'status' => 'pass',
            'detail' => 'connected successfully',
            'hint' => null,
        ];
    }

    /**
     * @return array{label:string, status:string, detail:string, hint:?string}
     */
    private function sendTest(?string $recipient): array
    {
        $recipient ??= config('mail.from.address') ?: 'test@example.com';

        try {
            Mail::to($recipient)->send(new MailSetupTestMail);

            return [
                'label' => 'Test email',
                'status' => 'pass',
                'detail' => "accepted for delivery to {$recipient}",
                'hint' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'label' => 'Test email',
                'status' => 'fail',
                'detail' => $e->getMessage(),
                'hint' => 'Verify the App Password is correct and 2-Step Verification is enabled on the Google account.',
            ];
        }
    }
}
