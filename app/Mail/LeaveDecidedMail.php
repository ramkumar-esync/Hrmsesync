<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the employee when HR approves or rejects their leave. The same mail
 * covers both outcomes — $approved decides the wording and the note shown.
 */
final class LeaveDecidedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $name,
        public bool $approved,
        public string $leaveType,
        public string $dates,
        public ?string $note,
        public string $appUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->approved
                ? 'Your leave has been approved'
                : 'Your leave request was not approved',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.leave-decided',
            with: [
                'name' => $this->name,
                'approved' => $this->approved,
                'leaveType' => $this->leaveType,
                'dates' => $this->dates,
                'note' => $this->note,
                'appUrl' => $this->appUrl,
            ],
        );
    }
}