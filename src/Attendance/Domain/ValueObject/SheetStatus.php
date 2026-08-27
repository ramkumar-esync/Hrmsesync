<?php

declare(strict_types=1);

namespace HR\Attendance\Domain\ValueObject;

/**
 * The lifecycle of a monthly attendance sheet.
 *
 *   Draft      — the employee is still filling it in; freely editable.
 *   Submitted  — handed to HR; locked to the employee, awaiting a decision.
 *   Approved   — HR accepted it; final.
 *   Returned   — HR sent it back with a note; editable again, then resubmitted.
 *
 * Returned is deliberately distinct from Draft so the employee sees it was
 * looked at and why, rather than it silently reverting.
 */
enum SheetStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Returned = 'returned';

    /** Can the employee edit entries in this state? */
    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Returned;
    }

    /** Is HR waiting to decide on this sheet? */
    public function awaitsDecision(): bool
    {
        return $this === self::Submitted;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Awaiting HR',
            self::Approved => 'Approved',
            self::Returned => 'Returned for changes',
        };
    }
}
