<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\MailHealthChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MailHealthController extends Controller
{
    public function show(MailHealthChecker $checker): View
    {
        return view('admin.mail-health', [
            'report' => $checker->run(),
        ]);
    }

    public function sendTest(MailHealthChecker $checker, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'email'],
        ]);

        $recipient = $validated['email'] ?? config('mail.from.address');
        if (blank($recipient)) {
            return back()->withErrors([
                'email' => 'Enter a recipient email or configure MAIL_FROM_ADDRESS first.',
            ]);
        }

        $report = $checker->run(sendTest: true, testRecipient: $recipient);

        $testCheck = collect($report['checks'])->firstWhere('label', 'Test email');
        $status = $testCheck['status'] ?? 'fail';

        // Sending real mail through the office's own credentials is exactly the
        // kind of action the audit trail exists for.
        AuditLog::record(
            'mail.test_sent',
            $request->user()?->id,
            $request->user()?->email,
            $request->ip(),
            $request->userAgent(),
            ['recipient' => $recipient, 'status' => $status],
        );

        if ($status === 'pass') {
            return back()->with('success', 'Test email sent — check the inbox (and spam folder) at '.$recipient.'.');
        }

        return back()->withErrors([
            'email' => 'Test email failed: '.($testCheck['detail'] ?? 'unknown error'),
        ]);
    }
}
