<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Lost-card bridge for office two-factor sign-in.
 *
 * Sent synchronously on purpose (same reason as
 * ResetPasswordCodeNotification): a queued notification would persist the
 * plaintext code in the `jobs` table, and inline delivery lets the caller
 * withdraw the code when the mail cannot be handed to the mailer.
 *
 * This code lives in the two-factor login-code cache, never in
 * `password_reset_tokens` — a reset code never validates here and this code
 * never validates at the password-reset form.
 */
class TwoFactorLoginCodeNotification extends Notification
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
        return (new MailMessage)
            ->subject('Your Sign-In Code — Barangay Management System')
            ->greeting('Your sign-in code')
            ->line('Enter this one-time code to finish signing in to your office account:')
            ->line($this->code)
            ->line("This code expires in {$this->expiresInMinutes} minutes and works only once. If you did not just sign in, ignore this message and tell your administrator.")
            ->salutation('Barangay Management System');
    }
}
