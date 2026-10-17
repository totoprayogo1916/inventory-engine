<?php

declare(strict_types=1);

namespace Ttpryg\InventoryEngine\Repositories;

use PDO;
use Ttpryg\InventoryEngine\Contracts\TransactionManagerInterface;

class PdoTransactionManager implements TransactionManagerInterface
{
    public function __construct(private readonly PDO $pdo) {}

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }

    public function isInTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }
}
