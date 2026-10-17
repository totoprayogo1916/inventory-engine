<?php

declare(strict_types=1);

namespace Ttpryg\InventoryEngine\Entities;

use DateTimeImmutable;
use Ttpryg\InventoryEngine\Enums\ReservationStatus;

class StockReservation
{
    public function __construct(
        public readonly string $id,
        public readonly string $inventoryStockId,
        public readonly string $referenceType,
        public readonly string $referenceId,
        public readonly int $quantity,
        public ReservationStatus $status,
        public DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null
    ) {
        $this->createdAt = $createdAt ?? new DateTimeImmutable;
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable;
    }

    public DateTimeImmutable $createdAt;

    public DateTimeImmutable $updatedAt;

    public function isExpired(?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable;

        return $this->status === ReservationStatus::ACTIVE && $now > $this->expiresAt;
    }
}
