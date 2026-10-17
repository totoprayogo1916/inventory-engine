<?php

namespace Ttpryg\InventoryEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Ttpryg\InventoryEngine\Enums\ReservationStatus;
use Ttpryg\InventoryEngine\Repositories\MemoryInventoryLocationRepository;
use Ttpryg\InventoryEngine\Repositories\MemoryInventoryStockRepository;
use Ttpryg\InventoryEngine\Repositories\MemoryStockMovementRepository;
use Ttpryg\InventoryEngine\Repositories\MemoryStockReservationRepository;
use Ttpryg\InventoryEngine\Services\InventoryService;

class InventoryServiceTest extends TestCase
{
    private InventoryService $service;

    private MemoryInventoryStockRepository $stockRepo;

    private MemoryStockReservationRepository $reservationRepo;

    private MemoryStockMovementRepository $movementRepo;

    protected function setUp(): void
    {
        $locationRepo = new MemoryInventoryLocationRepository;
        $this->stockRepo = new MemoryInventoryStockRepository;
        $this->reservationRepo = new MemoryStockReservationRepository;
        $this->movementRepo = new MemoryStockMovementRepository;

        $this->service = new InventoryService(
            $locationRepo,
            $this->stockRepo,
            $this->reservationRepo,
            $this->movementRepo
        );
    }

    public function test_reserve_and_commit_stock_flow(): void
    {
        $this->service->createLocation(id: 'loc-1', locationType: 'warehouse', locationId: 'wh-jkt', name: 'Gudang Jakarta', code: 'WH-JKT');
        $stock = $this->service->initializeStock(
            id: 'stk-1',
            locationType: 'warehouse',
            locationId: 'wh-jkt',
            productId: 'prod-100',
            initialQuantity: 50
        );

        $this->assertEquals(50, $stock->getQuantityAvailable());

        // Reserve 10 items
        $reservation = $this->service->reserveStock(
            reservationId: 'res-1',
            locationType: 'warehouse',
            locationId: 'wh-jkt',
            productId: 'prod-100',
            quantity: 10,
            referenceType: 'order',
            referenceId: 'order-99'
        );

        $this->assertEquals(40, $stock->getQuantityAvailable());
        $this->assertEquals(10, $stock->quantityReserved);
        $this->assertEquals(ReservationStatus::ACTIVE, $reservation->status);

        // Commit Reservation (payment completed)
        $this->service->commitReservation('res-1');

        $this->assertEquals(40, $stock->quantityOnHand);
        $this->assertEquals(0, $stock->quantityReserved);
        $this->assertEquals(ReservationStatus::COMMITTED, $reservation->status);
    }

    public function test_transfer_stock_between_locations(): void
    {
        $this->service->createLocation(id: 'loc-1', locationType: 'warehouse', locationId: 'wh-jkt', name: 'Gudang Jakarta', code: 'WH-JKT');
        $this->service->createLocation(id: 'loc-2', locationType: 'store', locationId: 'store-a', name: 'Toko A', code: 'STORE-A');

        $sourceStock = $this->service->initializeStock(
            id: 'stk-src',
            locationType: 'warehouse',
            locationId: 'wh-jkt',
            productId: 'prod-300',
            initialQuantity: 100
        );

        $this->service->transferStock(
            productId: 'prod-300',
            fromLocationType: 'warehouse',
            fromLocationId: 'wh-jkt',
            toLocationType: 'store',
            toLocationId: 'store-a',
            quantity: 30,
            note: 'Stocking up Store A'
        );

        $this->assertEquals(70, $sourceStock->quantityOnHand);
    }

    public function test_expire_reservation_releases_reserved_stock(): void
    {
        $stock = $this->service->initializeStock(
            id: 'stk-1',
            locationType: 'warehouse',
            locationId: 'wh-jkt',
            productId: 'prod-100',
            initialQuantity: 50
        );

        $reservation = $this->service->reserveStock(
            reservationId: 'res-1',
            locationType: 'warehouse',
            locationId: 'wh-jkt',
            productId: 'prod-100',
            quantity: 10,
            referenceType: 'order',
            referenceId: 'order-99',
            ttlMinutes: -1
        );

        $this->service->expireReservation('res-1');

        $this->assertEquals(ReservationStatus::EXPIRED, $reservation->status);
        $this->assertEquals(0, $stock->quantityReserved);
        $this->assertEquals(50, $stock->getQuantityAvailable());

        $movements = $this->movementRepo->findByStock('stk-1');
        $this->assertCount(3, $movements);
        $this->assertEquals('in', $movements[0]->type->value);
        $this->assertEquals('reserve', $movements[1]->type->value);
        $this->assertEquals('release', $movements[2]->type->value);
        $this->assertEquals('Reservation expired', $movements[2]->note);
    }

    public function test_commit_reservation_rejected_when_expired(): void
    {
        $stock = $this->service->initializeStock(
            id: 'stk-1',
            locationType: 'warehouse',
            locationId: 'wh-jkt',
            productId: 'prod-100',
            initialQuantity: 50
        );

        $reservation = $this->service->reserveStock(
            reservationId: 'res-1',
            locationType: 'warehouse',
            locationId: 'wh-jkt',
            productId: 'prod-100',
            quantity: 10,
            referenceType: 'order',
            referenceId: 'order-99',
            ttlMinutes: -1
        );

        try {
            $this->service->commitReservation('res-1');
            $this->fail('Expected RuntimeException for expired reservation');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('has expired', $exception->getMessage());
        }

        $this->assertEquals(ReservationStatus::ACTIVE, $reservation->status);
        $this->assertEquals(10, $stock->quantityReserved);
        $this->assertEquals(50, $stock->quantityOnHand);
    }

    public function test_process_expired_reservations_expires_only_expired(): void
    {
        $stock = $this->service->initializeStock(
            id: 'stk-1',
            locationType: 'warehouse',
            locationId: 'wh-jkt',
            productId: 'prod-100',
            initialQuantity: 50
        );

        $this->service->reserveStock(
            reservationId: 'res-1',
            locationType: 'warehouse',
            locationId: 'wh-jkt',
            productId: 'prod-100',
            quantity: 10,
            referenceType: 'order',
            referenceId: 'order-99',
            ttlMinutes: -1
        );
        $this->service->reserveStock(
            reservationId: 'res-2',
            locationType: 'warehouse',
            locationId: 'wh-jkt',
            productId: 'prod-100',
            quantity: 5,
            referenceType: 'order',
            referenceId: 'order-100',
            ttlMinutes: -1
        );
        $this->service->reserveStock(
            reservationId: 'res-3',
            locationType: 'warehouse',
            locationId: 'wh-jkt',
            productId: 'prod-100',
            quantity: 3,
            referenceType: 'order',
            referenceId: 'order-101',
            ttlMinutes: 15
        );

        $count = $this->service->processExpiredReservations();

        $this->assertEquals(2, $count);
        $this->assertEquals(3, $stock->quantityReserved);
        $this->assertEquals(47, $stock->getQuantityAvailable());
        $this->assertEquals(ReservationStatus::EXPIRED, $this->reservationRepo->findById('res-1')?->status);
        $this->assertEquals(ReservationStatus::EXPIRED, $this->reservationRepo->findById('res-2')?->status);
        $this->assertEquals(ReservationStatus::ACTIVE, $this->reservationRepo->findById('res-3')?->status);
    }

    public function test_expire_nonexistent_reservation_throws(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service->expireReservation('res-missing');
    }

    public function test_expire_already_released_reservation_is_noop(): void
    {
        $stock = $this->service->initializeStock(
            id: 'stk-1',
            locationType: 'warehouse',
            locationId: 'wh-jkt',
            productId: 'prod-100',
            initialQuantity: 50
        );

        $reservation = $this->service->reserveStock(
            reservationId: 'res-1',
            locationType: 'warehouse',
            locationId: 'wh-jkt',
            productId: 'prod-100',
            quantity: 10,
            referenceType: 'order',
            referenceId: 'order-99'
        );

        $this->service->releaseReservation('res-1');
        $this->service->expireReservation('res-1');

        $this->assertEquals(ReservationStatus::RELEASED, $reservation->status);
        $this->assertEquals(0, $stock->quantityReserved);
        $this->assertEquals(50, $stock->getQuantityAvailable());
    }
}
