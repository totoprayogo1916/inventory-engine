# InventoryEngine Library (`ttpryg/inventory-engine`)

`ttpryg/inventory-engine` is a framework-agnostic standalone PHP 8.1+ library for Inventory Location management (Warehouses, Stores, Consignment, etc.), Polymorphic Stock Scoping, Stock Reservations (holding stock during checkout), Stock Movements, Stock Transfers, and Low Stock Alerts.

## 🌟 Key Features

- **Framework Agnostic**: Works out of the box with any PHP 8.1+ application or framework (Vanilla PHP, Slim 4, Laravel, Symfony, CodeIgniter).
- **Polymorphic Location Scoping**: Scoping stock levels flexibly via `location_type` (`warehouse`, `store`, `consignment`, etc.) and `location_id` without coupling to fixed database schemas.
- **Stock Reservation (Hold Stock)**: Reserve stock during checkout (`reserveStock`) for $N$ minutes, preventing overselling across concurrent checkout sessions.
- **Commit & Release Pipeline**:
  - `commitReservation()`: Permanently deduct physical stock on hand when payment completes.
  - `releaseReservation()`: Restore reserved stock if cart/order is cancelled or expired.
- **Stock Transfers**: Transfer stock between locations (`transferStock()`) with symmetric audit logs.
- **Stock Movement Audit Log**: Immutable record of all stock transactions (`in`, `out`, `reserve`, `commit`, `release`, `transfer`, `adjustment`).
- **Low Stock & Reorder Point Alerts**: Dispatches `LowStockAlertEvent` when available stock drops to or below threshold.
- **Multiple Storage Drivers**: PDO Driver (MariaDB, MySQL, SQLite) & In-Memory Driver for fast unit testing.
- **PSR-14 Event Integration**: Integrated with `ttpryg/event-dispatcher`.

---

## 🗄️ Database Schema

Schema files live in `database/`, one per driver:

| Driver | Schema file |
|---|---|
| SQLite | `database/schema.sqlite.sql` |
| MySQL 8 / MariaDB | `database/schema.mysql.sql` |

Both scripts are idempotent (`CREATE TABLE IF NOT EXISTS`; on MySQL/MariaDB the indexes are defined inline so re-running is safe — MySQL does not support `CREATE INDEX IF NOT EXISTS`).

Tables: `inventory_locations`, `inventory_stocks`, `stock_reservations`, `stock_movements` (with indexes for stock lookup, reservation expiry scans, reference lookups, and movement audits).

---

## 🚀 Quick Usage Example

```php
use PDO;
use Ttpryg\InventoryEngine\Repositories\PdoInventoryLocationRepository;
use Ttpryg\InventoryEngine\Repositories\PdoInventoryStockRepository;
use Ttpryg\InventoryEngine\Repositories\PdoStockReservationRepository;
use Ttpryg\InventoryEngine\Repositories\PdoStockMovementRepository;
use Ttpryg\InventoryEngine\Services\InventoryService;

// 1. Initialize PDO & Repositories
$pdo = new PDO("mysql:host=localhost;dbname=my_db", "root", "secret");

$locationRepo    = new PdoInventoryLocationRepository($pdo);
$stockRepo       = new PdoInventoryStockRepository($pdo);
$reservationRepo = new PdoStockReservationRepository($pdo);
$movementRepo    = new PdoStockMovementRepository($pdo);

$service = new InventoryService($locationRepo, $stockRepo, $reservationRepo, $movementRepo);

// 2. Create Locations (Warehouse / Store / Consignment)
$service->createLocation(
    id: 'loc-001',
    locationType: 'warehouse',
    locationId: 'wh-jkt',
    name: 'Gudang Utama Jakarta',
    code: 'WH-JKT'
);

// 3. Initialize Stock
$stock = $service->initializeStock(
    id: 'stk-001',
    locationType: 'warehouse',
    locationId: 'wh-jkt',
    productId: 'prod-100',
    initialQuantity: 100,
    minStockAlert: 10
);

// 4. Reserve Stock during Checkout (Expires in 15 minutes)
$reservation = $service->reserveStock(
    reservationId: 'res-001',
    locationType: 'warehouse',
    locationId: 'wh-jkt',
    productId: 'prod-100',
    quantity: 2,
    referenceType: 'order',
    referenceId: 'order-123',
    ttlMinutes: 15
);

// 5. Commit Stock on Payment Completion
$service->commitReservation('res-001');

// 6. Transfer Stock between Locations
$service->transferStock(
    sourceLocationType: 'warehouse',
    sourceLocationId: 'wh-jkt',
    targetLocationType: 'store',
    targetLocationId: 'store-a',
    productId: 'prod-100',
    quantity: 10,
    note: 'Restock Store A'
);
```

---

## 🧪 Testing

Run PHPUnit test suite (SQLite in-memory by default):

```bash
vendor/bin/phpunit
```

Or using Docker Compose — this runs the same suite against the bundled MariaDB service:

```bash
docker compose up -d
docker compose exec app vendor/bin/phpunit
```

To run the integration tests against your own MySQL/MariaDB instance, point the `TEST_DB_*` environment variables at it:

```bash
TEST_DB_DSN='mysql:host=127.0.0.1;dbname=inventory_engine;charset=utf8mb4' \
TEST_DB_USER=root TEST_DB_PASS=secret \
vendor/bin/phpunit
```

---

## 📄 License

MIT License.
