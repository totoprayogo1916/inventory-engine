<?php

declare(strict_types=1);

namespace Ttpryg\InventoryEngine\Entities;

use DateTimeImmutable;
use Ttpryg\InventoryEngine\Exceptions\InsufficientStockException;
use Ttpryg\InventoryEngine\Exceptions\InvalidQuantityException;

class InventoryStock
{
    public function __construct(
        public readonly string $id,
        public readonly string $locationType,
        public readonly string $locationId,
        public readonly string $productId,
        public ?string $variantId = null,
        public ?string $sku = null,
        public int $quantityOnHand = 0,
        public int $quantityReserved = 0,
        public int $minStockAlert = 5,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null
    ) {
        $this->createdAt = $createdAt ?? new DateTimeImmutable;
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable;
    }

    public function getQuantityAvailable(): int
    {
        return $this->quantityOnHand - $this->quantityReserved;
    }

    public function isLowStock(): bool
    {
        return $this->getQuantityAvailable() <= $this->minStockAlert;
    }

    public function reserve(int $quantity): void
    {
        $this->guardPositiveQuantity($quantity);

        $this->quantityReserved += $quantity;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function commit(int $quantity): void
    {
        $this->guardReservedQuantity($quantity, 'commit');

        $this->quantityOnHand -= $quantity;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function release(int $quantity): void
    {
        $this->guardReservedQuantity($quantity, 'release');

        $this->updatedAt = new DateTimeImmutable;
    }

    public function adjust(int $delta): void
    {
        if ($this->quantityOnHand + $delta < 0) {
            throw new InsufficientStockException(
                -$delta,
                $this->quantityOnHand,
                "Adjustment of {$delta} would make on-hand stock negative (on hand: {$this->quantityOnHand})"
            );
        }

        $this->quantityOnHand += $delta;
        $this->updatedAt = new DateTimeImmutable;
    }

    private function guardPositiveQuantity(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new InvalidQuantityException("Quantity must be greater than 0 (got {$quantity})");
        }
    }

    private function guardReservedQuantity(int $quantity, string $operation): void
    {
        $this->guardPositiveQuantity($quantity);

        if ($quantity > $this->quantityReserved) {
            throw new InsufficientStockException(
                $quantity,
                $this->quantityReserved,
                "Cannot {$operation} {$quantity}: only {$this->quantityReserved} reserved"
            );
        }

        $this->quantityReserved -= $quantity;
    }
}
