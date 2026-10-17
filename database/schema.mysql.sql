-- MySQL 8 / MariaDB schema for ttpryg/inventory-engine
-- Indexes are defined inline (KEY ...) so the script stays idempotent:
-- MySQL does not support "CREATE INDEX IF NOT EXISTS", but "CREATE TABLE
-- IF NOT EXISTS" skips the inline indexes when the table already exists.
CREATE TABLE IF NOT EXISTS inventory_locations (
    id VARCHAR(36) PRIMARY KEY,
    location_type VARCHAR(50) NOT NULL, -- warehouse, store, consignment, supplier
    location_id VARCHAR(100) NOT NULL,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    address TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_location_type_location_id (location_type, location_id)
);

CREATE TABLE IF NOT EXISTS inventory_stocks (
    id VARCHAR(36) PRIMARY KEY,
    location_type VARCHAR(50) NOT NULL,
    location_id VARCHAR(100) NOT NULL,
    product_id VARCHAR(36) NOT NULL,
    variant_id VARCHAR(36) NULL,
    sku VARCHAR(100) NULL,
    quantity_on_hand INT NOT NULL DEFAULT 0,
    quantity_reserved INT NOT NULL DEFAULT 0,
    min_stock_alert INT NOT NULL DEFAULT 5,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_location_stock (location_type, location_id, product_id, variant_id)
);

CREATE TABLE IF NOT EXISTS stock_reservations (
    id VARCHAR(36) PRIMARY KEY,
    inventory_stock_id VARCHAR(36) NOT NULL,
    reference_type VARCHAR(50) NOT NULL, -- cart, order
    reference_id VARCHAR(36) NOT NULL,
    quantity INT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active', -- active, committed, released, expired
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_reservation_status_expires (status, expires_at),
    KEY idx_reservation_reference (reference_type, reference_id),
    CONSTRAINT fk_stock_reservations_stock FOREIGN KEY (inventory_stock_id) REFERENCES inventory_stocks(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS stock_movements (
    id VARCHAR(36) PRIMARY KEY,
    inventory_stock_id VARCHAR(36) NOT NULL,
    location_type VARCHAR(50) NOT NULL,
    location_id VARCHAR(100) NOT NULL,
    type VARCHAR(30) NOT NULL, -- in, out, reserve, commit, release, transfer, adjustment
    quantity INT NOT NULL,
    reference_type VARCHAR(50) NULL,
    reference_id VARCHAR(36) NULL,
    note TEXT NULL,
    actor_id VARCHAR(36) NULL,
    created_at DATETIME NOT NULL,
    KEY idx_movement_stock_created (inventory_stock_id, created_at),
    CONSTRAINT fk_stock_movements_stock FOREIGN KEY (inventory_stock_id) REFERENCES inventory_stocks(id) ON DELETE CASCADE
);
