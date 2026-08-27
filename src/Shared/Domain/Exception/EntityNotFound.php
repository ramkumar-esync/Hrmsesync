<?php

declare(strict_types=1);

namespace HR\Shared\Domain\Exception;

class EntityNotFound extends DomainException
{
    public function statusCode(): int
    {
        return 404;
    }
}
