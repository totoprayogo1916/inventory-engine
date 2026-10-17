<?php

declare(strict_types=1);

namespace Ttpryg\InventoryEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ttpryg\InventoryEngine\Support\Uuid;

class UuidTest extends TestCase
{
    public function test_uuid4_has_canonical_v4_format(): void
    {
        $uuid = Uuid::uuid4();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid
        );
        $this->assertLessThanOrEqual(36, strlen($uuid));
    }

    public function test_uuid4_is_unique(): void
    {
        $ids = [];
        for ($i = 0; $i < 1000; $i++) {
            $ids[] = Uuid::uuid4();
        }

        $this->assertCount(1000, array_unique($ids));
    }
}
