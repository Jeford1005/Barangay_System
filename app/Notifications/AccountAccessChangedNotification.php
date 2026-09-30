<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class AccountAccessChangedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $change,
        public ?string $fromRole = null,
        public ?string $toRole = null,
        public ?string $reason = null,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $subject = match ($this->change) {
            'suspended' => 'Your barangay account has been suspended',
            'reactivated' => 'Your barangay account has been reactivated',
            'role_changed' => 'Your barangay account role has changed',
            default => 'Your barangay account has been updated',
        };

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.account-access-changed', [
                'userName' => $this->userName ?: 'Resident',
                'change' => $this->change,
                'fromRole' => $this->fromRole,
                'toRole' => $this->toRole,
                'reason' => $this->reason,
            ]);
    }
}
