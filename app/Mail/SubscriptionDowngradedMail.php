<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionDowngradedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('messages.mail.downgraded.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.subscription-downgraded',
            with: [
                'sub'        => $this->subscription,
                'org'        => $this->subscription->organization,
                'reason'     => $this->reason,
                'billingUrl' => route('dashboard.billing.index'),
            ],
        );
    }
}
