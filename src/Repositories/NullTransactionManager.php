<?php

declare(strict_types=1);

namespace Ttpryg\InventoryEngine\Repositories;

use Ttpryg\InventoryEngine\Contracts\TransactionManagerInterface;

class NullTransactionManager implements TransactionManagerInterface
{
    public function beginTransaction(): void {}

    public function commit(): void {}

    public function rollBack(): void {}

    public function isInTransaction(): bool
    {
        return false;
    }
}
