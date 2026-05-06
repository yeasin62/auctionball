<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('messages.mail.invitation.subject', [
                'org' => $this->invitation->organization->name,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invitation',
            with: [
                'org'        => $this->invitation->organization,
                'role'       => $this->invitation->role,
                'team'       => $this->invitation->team,
                'invitedBy'  => $this->invitation->invitedBy,
                'acceptUrl'  => route('invite.show', $this->invitation->token),
                'expiresAt'  => $this->invitation->expires_at?->format('F j, Y'),
            ],
        );
    }
}
