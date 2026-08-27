<?php

declare(strict_types=1);

namespace HR\Shared\Infrastructure;

use HR\Shared\Application\TransactionManager;
use Illuminate\Database\DatabaseManager;

final readonly class DatabaseTransactionManager implements TransactionManager
{
    public function __construct(private DatabaseManager $database) {}

    public function transactional(callable $work): mixed
    {
        return $this->database->transaction($work);
    }
}
