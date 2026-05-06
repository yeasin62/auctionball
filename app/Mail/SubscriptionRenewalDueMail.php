<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionRenewalDueMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Subscription $subscription) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('messages.mail.renewal_due.subject', [
                'plan' => ucfirst($this->subscription->plan),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.subscription-renewal-due',
            with: [
                'sub'        => $this->subscription,
                'org'        => $this->subscription->organization,
                'billingUrl' => route('dashboard.billing.index'),
            ],
        );
    }
}
