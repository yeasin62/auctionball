<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionRenewedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Subscription $subscription) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('messages.mail.renewed.subject', [
                'plan' => ucfirst($this->subscription->plan),
            ]),
        );
    }

    public function content(): Content
    {
        $sub = $this->subscription;
        // Subscriptions store their own currency (USD for PayPal, BDT for bKash/dev)
        // — use that, not the org's display_currency. We only translate digits.
        $symbol = $sub->currency === 'USD' ? '$' : '৳';
        $loc = app()->getLocale() === 'bn' ? 'bn-IN' : 'en-IN';
        $f = new \NumberFormatter($loc, \NumberFormatter::DECIMAL);

        return new Content(
            markdown: 'mail.subscription-renewed',
            with: [
                'sub'    => $sub,
                'org'    => $sub->organization,
                'amount' => $symbol . $f->format($sub->amount),
            ],
        );
    }
}
