<?php

namespace App\Notifications;

use App\Models\CertificateRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class CertificateRequestDecisionNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public CertificateRequest $certificateRequest,
        public bool $approved,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $request = $this->certificateRequest->load(['document', 'issuance']);
        $title = $request->document->title;

        if ($this->approved) {
            return (new MailMessage)
                ->subject("Your {$title} request #{$request->id} has been approved")
                ->view('emails.certificate-request-approved', [
                    'userName' => $notifiable->name ?: 'Resident',
                    'request' => $request,
                ]);
        }

        return (new MailMessage)
            ->subject("Your {$title} request #{$request->id} was not approved")
            ->view('emails.certificate-request-rejected', [
                'userName' => $notifiable->name ?: 'Resident',
                'request' => $request,
                'reason' => $request->rejection_reason,
            ]);
    }
}
