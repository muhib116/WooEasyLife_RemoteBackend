<?php

namespace App\Mail;

use App\Models\SubscriptionInquiry;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LandingOrderConvertedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SubscriptionInquiry $inquiry,
        public User $merchant,
        public bool $userCreated,
    ) {
        $this->inquiry->loadMissing('packageHub:id,title');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your WooEasyLife account is ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.landing-order-converted',
            with: [
                'inquiry' => $this->inquiry,
                'merchant' => $this->merchant,
                'userCreated' => $this->userCreated,
                'planTitle' => $this->inquiry->packageHub?->title,
                'loginUrl' => route('merchant.login'),
                'portalUrl' => url('/portal'),
            ],
        );
    }
}
