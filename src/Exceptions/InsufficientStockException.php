<?php

declare(strict_types=1);

namespace Ttpryg\InventoryEngine\Exceptions;

class InsufficientStockException extends InventoryException
{
    public function __construct(
        public readonly int $requested,
        public readonly int $available,
        string $message = ''
    ) {
        parent::__construct(
            $message !== '' ? $message : "Insufficient available stock (Requested: {$requested}, Available: {$available})"
        );
    }
}
