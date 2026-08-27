<?php

declare(strict_types=1);

namespace HR\Attendance\Domain\Exception;

use HR\Shared\Domain\Exception\DomainException;

/**
 * Raised on submit when a row claims a leave type the employee has no approved
 * leave for on that date. The message lists the offending days so the employee
 * can fix them or apply for the leave first.
 */
final class LeaveDoesNotReconcile extends DomainException
{
    /** @param list<string> $problems */
    public static function on(array $problems): self
    {
        $detail = implode('; ', $problems);

        return new self(
            'Some leave rows do not match approved leave: '.$detail.
            '. Apply for and get the leave approved first, or correct these rows.',
        );
    }
}
