<?php

declare(strict_types=1);

namespace Ttpryg\InventoryEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ttpryg\InventoryEngine\Entities\InventoryStock;
use Ttpryg\InventoryEngine\Exceptions\InsufficientStockException;
use Ttpryg\InventoryEngine\Exceptions\InvalidQuantityException;

class InventoryStockTest extends TestCase
{
    private function makeStock(int $onHand = 50, int $reserved = 0): InventoryStock
    {
        return new InventoryStock(
            id: 'stk-1',
            locationType: 'warehouse',
            locationId: 'wh-jkt',
            productId: 'prod-100',
            quantityOnHand: $onHand,
            quantityReserved: $reserved
        );
    }

    public function test_get_quantity_available_is_not_clamped(): void
    {
        $stock = $this->makeStock(onHand: 5, reserved: 8);

        $this->assertEquals(-3, $stock->getQuantityAvailable());
    }

    public function test_reserve_rejects_zero(): void
    {
        $stock = $this->makeStock();

        $this->expectException(InvalidQuantityException::class);
        $stock->reserve(0);
    }

    public function test_reserve_rejects_negative(): void
    {
        $stock = $this->makeStock();

        $this->expectException(InvalidQuantityException::class);
        $stock->reserve(-5);
    }

    public function test_reserve_increases_reserved(): void
    {
        $stock = $this->makeStock(onHand: 50);

        $stock->reserve(10);

        $this->assertEquals(10, $stock->quantityReserved);
        $this->assertEquals(40, $stock->getQuantityAvailable());
    }

    public function test_commit_rejects_zero(): void
    {
        $stock = $this->makeStock(reserved: 10);

        $this->expectException(InvalidQuantityException::class);
        $stock->commit(0);
    }

    public function test_commit_rejects_more_than_reserved(): void
    {
        $stock = $this->makeStock(onHand: 50, reserved: 10);

        try {
            $stock->commit(15);
            $this->fail('Expected InsufficientStockException');
        } catch (InsufficientStockException $exception) {
            $this->assertEquals(15, $exception->requested);
            $this->assertEquals(10, $exception->available);
        }

        $this->assertEquals(10, $stock->quantityReserved);
        $this->assertEquals(50, $stock->quantityOnHand);
    }

    public function test_commit_deducts_reserved_and_on_hand(): void
    {
        $stock = $this->makeStock(onHand: 50, reserved: 10);

        $stock->commit(10);

        $this->assertEquals(0, $stock->quantityReserved);
        $this->assertEquals(40, $stock->quantityOnHand);
    }

    public function test_release_rejects_zero(): void
    {
        $stock = $this->makeStock(reserved: 10);

        $this->expectException(InvalidQuantityException::class);
        $stock->release(0);
    }

    public function test_release_rejects_more_than_reserved(): void
    {
        $stock = $this->makeStock(onHand: 50, reserved: 10);

        $this->expectException(InsufficientStockException::class);
        $stock->release(15);
    }

    public function test_release_frees_reserved_without_touching_on_hand(): void
    {
        $stock = $this->makeStock(onHand: 50, reserved: 10);

        $stock->release(10);

        $this->assertEquals(0, $stock->quantityReserved);
        $this->assertEquals(50, $stock->quantityOnHand);
        $this->assertEquals(50, $stock->getQuantityAvailable());
    }

    public function test_adjust_rejects_resulting_negative_on_hand(): void
    {
        $stock = $this->makeStock(onHand: 50);

        try {
            $stock->adjust(-60);
            $this->fail('Expected InsufficientStockException');
        } catch (InsufficientStockException $exception) {
            $this->assertEquals(60, $exception->requested);
            $this->assertEquals(50, $exception->available);
        }

        $this->assertEquals(50, $stock->quantityOnHand);
    }

    public function test_adjust_allows_reaching_zero(): void
    {
        $stock = $this->makeStock(onHand: 50);

        $stock->adjust(-50);

        $this->assertEquals(0, $stock->quantityOnHand);
        $this->assertEquals(0, $stock->getQuantityAvailable());
        $this->assertTrue($stock->isLowStock());
    }

    public function test_adjust_positive_and_negative(): void
    {
        $stock = $this->makeStock(onHand: 50);

        $stock->adjust(20);
        $this->assertEquals(70, $stock->quantityOnHand);

        $stock->adjust(-30);
        $this->assertEquals(40, $stock->quantityOnHand);
    }
}
