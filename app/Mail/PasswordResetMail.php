<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to an employee when HR or their manager resets their password. Carries
 * the fresh temporary password so they can sign in and set their own.
 */
final class PasswordResetMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $name,
        public string $email,
        public string $appUrl,
        public string $temporaryPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your '.config('app.name').' password was reset',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.password-reset',
            with: [
                'name' => $this->name,
                'email' => $this->email,
                'appUrl' => $this->appUrl,
                'password' => $this->temporaryPassword,
            ],
        );
    }
}