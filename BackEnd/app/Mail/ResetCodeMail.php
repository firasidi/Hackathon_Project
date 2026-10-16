<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// The email that carries the 6-digit password reset code
class ResetCodeMail extends Mailable
{
    public function __construct(
        public string $code,
        public ?string $studentName = null,
        public int $minutes = 10,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Insaf - Code de réinitialisation | رمز إعادة التعيين');
    }

    public function content(): Content
    {
        return new Content(view: 'reset-code');
    }
}