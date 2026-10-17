<?php

declare(strict_types=1);

namespace Ttpryg\InventoryEngine\Entities;

use DateTimeImmutable;

class InventoryLocation
{
    public function __construct(
        public readonly string $id,
        public string $locationType,
        public string $locationId,
        public string $name,
        public string $code,
        public ?string $address = null,
        public bool $isActive = true,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null
    ) {
        $this->createdAt = $createdAt ?? new DateTimeImmutable;
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable;
    }

    public DateTimeImmutable $createdAt;

    public DateTimeImmutable $updatedAt;
}
