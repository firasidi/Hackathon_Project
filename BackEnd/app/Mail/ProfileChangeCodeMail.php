<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ProfileChangeCodeMail extends Mailable
{
    public function __construct(public string $code, public int $minutes = 10) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Insaf - Confirmation de modification du profil');
    }

    public function content(): Content
    {
        return new Content(view: 'profile-change-code');
    }
}
