<?php

declare(strict_types=1);

namespace Ttpryg\InventoryEngine\Contracts;

use Ttpryg\InventoryEngine\Entities\StockReservation;

interface StockReservationRepositoryInterface
{
    public function save(StockReservation $reservation): void;

    public function findById(string $id): ?StockReservation;

    /**
     * @return list<StockReservation>
     */
    public function findByReference(string $referenceType, string $referenceId): array;

    /**
     * @return list<StockReservation>
     */
    public function findExpiredActiveReservations(): array;
}
