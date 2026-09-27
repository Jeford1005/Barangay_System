<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent synchronously on purpose.
 *
 * A queued notification serializes its whole payload into the `jobs` table,
 * which would persist the plaintext reset code. Sending inline also means the
 * 15-minute TTL starts when the mail is actually handed to the mailer, and a
 * delivery failure surfaces to `PasswordResetCodeService`, which can then
 * withdraw the code instead of leaving an unreachable token behind.
 */
class ResetPasswordCodeNotification extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $code,
        public int $expiresInMinutes,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // `??` rather than a bare read: the notification is also sent to
        // anonymous routes (mail tests, cron), and those have no name to read.
        $userName = $notifiable->name ?? null;

        return (new MailMessage)
            ->subject('Your Password Reset Code — Barangay Management System')
            ->view('emails.reset-password-code', [
                'code' => $this->code,
                'expiresInMinutes' => $this->expiresInMinutes,
                'userName' => $userName,
            ])
            ->text('emails.reset-password-code-text', [
                'code' => $this->code,
                'expiresInMinutes' => $this->expiresInMinutes,
                'userName' => $userName,
            ]);
    }
}
