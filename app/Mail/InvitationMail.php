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
        $this->invitation->loadMissing(['family', 'inviter']);

        return new Envelope(
            subject: ($this->invitation->inviter?->name ?? 'A relative').' invited you to the '.$this->invitation->family->name.' family archive',
            replyTo: $this->invitation->inviter?->email ? [$this->invitation->inviter->email] : [],
        );
    }

    public function content(): Content
    {
        $this->invitation->loadMissing(['family', 'inviter', 'person']);

        return new Content(markdown: 'mail.invitation', with: [
            'inviter' => $this->invitation->inviter?->name ?? 'A relative',
            'family' => $this->invitation->family->name,
            'role' => $this->invitation->role,
            'person' => $this->invitation->person?->full_name,
            'url' => route('invitations.show', $this->invitation->token),
            'expires' => $this->invitation->expires_at?->toFormattedDateString(),
        ]);
    }
}
