<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The welcome email sent when an employee is created. It carries the one-time
 * password only when the account actually has a login — a record created
 * without a login account gets a plain welcome with no credentials.
 *
 * It implements ShouldQueue via Queueable so sending happens on the queue and a
 * slow SMTP server never holds up the "create employee" request. If no queue
 * worker is running, Laravel falls back to sending synchronously.
 */
final class EmployeeWelcomeMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $name,
        public string $email,
        public string $appUrl,
        public ?string $temporaryPassword = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to the '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.employee-welcome',
            with: [
                'name' => $this->name,
                'email' => $this->email,
                'appUrl' => $this->appUrl,
                'password' => $this->temporaryPassword,
            ],
        );
    }
}