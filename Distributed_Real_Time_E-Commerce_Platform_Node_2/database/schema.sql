CREATE DATABASE IF NOT EXISTS distributed_real_time_e_commerce_platform_node_2
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE distributed_real_time_e_commerce_platform_node_2;

-- Inventory owns this table. product_id is an identifier from the Catalog service;
-- intentionally no FK to the Orders/Catalog database.
CREATE TABLE IF NOT EXISTS inventory_items (
    product_id BIGINT UNSIGNED NOT NULL,
    available_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id)
) ENGINE=InnoDB;

-- A unique reference makes Reserve/Release idempotent across retries.
CREATE TABLE IF NOT EXISTS stock_reservations (
    reservation_reference VARCHAR(64) NOT NULL,
    items_hash CHAR(64) NULL,
    status ENUM('RESERVED', 'RELEASED') NOT NULL DEFAULT 'RESERVED',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    released_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (reservation_reference),
    INDEX idx_stock_reservations_status_created (status, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_reservation_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_reference VARCHAR(64) NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reservation_product (reservation_reference, product_id),
    CONSTRAINT fk_reservation_items_reservation
        FOREIGN KEY (reservation_reference)
        REFERENCES stock_reservations (reservation_reference)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_reservation_items_product (product_id)
) ENGINE=InnoDB;
