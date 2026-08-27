<?php

declare(strict_types=1);

namespace HR\Shared\Domain\Exception;

/**
 * Base class for every rule violation expressed by the domain layer.
 *
 * These are not server faults — they mean the caller asked for something the
 * business rules forbid, so they surface as 4xx responses.
 */
abstract class DomainException extends \DomainException
{
    public function statusCode(): int
    {
        return 422;
    }
}
