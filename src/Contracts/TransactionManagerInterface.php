<?php

declare(strict_types=1);

namespace Ttpryg\InventoryEngine\Contracts;

interface TransactionManagerInterface
{
    public function beginTransaction(): void;

    public function commit(): void;

    public function rollBack(): void;

    public function isInTransaction(): bool;
}
