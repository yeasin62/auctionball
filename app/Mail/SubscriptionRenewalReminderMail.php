<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionRenewalReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public int $days,
    ) {}

    public function envelope(): Envelope
    {
        $autoRenew = $this->subscription->auto_renew && $this->subscription->is_recurring;
        $key = $autoRenew ? 'messages.mail.reminder.subject_auto' : 'messages.mail.reminder.subject_manual';

        return new Envelope(subject: __($key, ['days' => $this->days]));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.subscription-renewal-reminder',
            with: [
                'sub'        => $this->subscription,
                'org'        => $this->subscription->organization,
                'days'       => $this->days,
                'autoRenew'  => $this->subscription->auto_renew && $this->subscription->is_recurring,
                'amount'     => ($this->subscription->currency === 'BDT' ? '৳' : '$') . number_format($this->subscription->amount),
                'billingUrl' => route('dashboard.billing.index'),
            ],
        );
    }
}
