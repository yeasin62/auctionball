<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionRenewalFailedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public string $reason,
        public int $attempt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('messages.mail.renewal_failed.subject', [
                'attempt' => $this->attempt,
                'max'     => Subscription::MAX_ATTEMPTS,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.subscription-renewal-failed',
            with: [
                'sub'         => $this->subscription,
                'org'         => $this->subscription->organization,
                'reason'      => $this->reason,
                'attempt'     => $this->attempt,
                'maxAttempts' => Subscription::MAX_ATTEMPTS,
                'graceUntil'  => $this->subscription->grace_until?->format('F j, Y'),
                'billingUrl'  => route('dashboard.billing.index'),
            ],
        );
    }
}
