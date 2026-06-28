<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $alert
     */
    public function __construct(
        public User $user,
        public ?string $domain,
        public array $alert
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->alert['severity'] ?? 'info') {
            'danger' => 'Action required: subscription alert',
            'warning' => 'Subscription reminder',
            default => 'Subscription update',
        };

        return new Envelope(
            subject: $subject . ($this->domain ? " ({$this->domain})" : ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.subscription-alert',
            with: [
                'merchantName' => $this->user->name,
                'domain' => $this->domain,
                'message' => $this->alert['message'] ?? '',
                'severity' => $this->alert['severity'] ?? 'info',
                'alertType' => $this->alert['type'] ?? 'unknown',
                'portalUrl' => rtrim((string) config('subscription.notifications.portal_url'), '/') . '/portal/billing',
            ],
        );
    }
}
