<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to HR (and cc'd naturally to whoever applies) when an employee files a
 * leave application, so it can be reviewed without waiting for someone to happen
 * to open the approvals page.
 */
final class LeaveAppliedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $employeeName,
        public string $leaveType,
        public string $dates,
        public float $workingDays,
        public ?string $reason,
        public string $appUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Leave request from '.$this->employeeName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.leave-applied',
            with: [
                'employeeName' => $this->employeeName,
                'leaveType' => $this->leaveType,
                'dates' => $this->dates,
                'workingDays' => $this->workingDays,
                'reason' => $this->reason,
                'appUrl' => $this->appUrl,
            ],
        );
    }
}