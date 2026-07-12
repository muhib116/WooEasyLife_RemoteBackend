<?php

namespace App\Mail;

use App\Models\SubscriptionInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionInquiryAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SubscriptionInquiry $inquiry
    ) {
        $this->inquiry->loadMissing('packageHub:id,title,package_duration');
    }

    public function envelope(): Envelope
    {
        $plan = $this->inquiry->packageHub?->title ?: 'Unknown plan';
        $domain = $this->inquiry->domain ?: 'no-domain';

        return new Envelope(
            subject: "New subscription request: {$plan} ({$domain})",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.subscription-inquiry-admin',
            with: [
                'inquiry' => $this->inquiry,
                'planTitle' => $this->inquiry->packageHub?->title,
                'adminOrdersUrl' => url('/orders'),
            ],
        );
    }
}
