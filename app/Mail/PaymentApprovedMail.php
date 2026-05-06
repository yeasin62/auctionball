<?php

namespace App\Mail;

use App\Models\PaymentTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the customer who submitted a manual bKash payment when super admin
 * approves it. Subscription is already active by the time this fires; the mail
 * is just a confirmation and a link back to the dashboard.
 */
class PaymentApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public PaymentTransaction $txn) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('messages.mail.payment_approved.subject', [
                'plan' => ucfirst($this->txn->plan),
            ]),
        );
    }

    public function content(): Content
    {
        $loc = app()->getLocale() === 'bn' ? 'bn-IN' : 'en-IN';
        $f = new \NumberFormatter($loc, \NumberFormatter::DECIMAL);

        return new Content(
            markdown: 'mail.payment-approved',
            with: [
                'txn'     => $this->txn,
                'org'     => $this->txn->organization,
                'amount'  => '৳' . $f->format($this->txn->amount),
            ],
        );
    }
}
