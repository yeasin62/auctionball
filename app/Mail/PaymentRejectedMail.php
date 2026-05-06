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
 * Sent when super admin rejects a manual bKash submission — usually because
 * the TrxID couldn't be verified. Includes the rejection reason if provided.
 */
class PaymentRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public PaymentTransaction $txn, public ?string $reason = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('messages.mail.payment_rejected.subject'),
        );
    }

    public function content(): Content
    {
        $loc = app()->getLocale() === 'bn' ? 'bn-IN' : 'en-IN';
        $f = new \NumberFormatter($loc, \NumberFormatter::DECIMAL);

        return new Content(
            markdown: 'mail.payment-rejected',
            with: [
                'txn'    => $this->txn,
                'org'    => $this->txn->organization,
                'amount' => '৳' . $f->format($this->txn->amount),
                'reason' => $this->reason,
            ],
        );
    }
}
