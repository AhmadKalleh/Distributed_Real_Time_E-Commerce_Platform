USE distributed_real_time_e_commerce_platform_node_2;
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE stock_reservations;
TRUNCATE TABLE inventory_items;
TRUNCATE TABLE stock_reservation_items;

SET FOREIGN_KEY_CHECKS = 1;

-- DEVELOPMENT ONLY: IDs and quantities must match real products in the Catalog DB.
INSERT INTO inventory_items (product_id, available_quantity)
VALUES
    (1, 10),
    (2, 5),
    (3, 1),
    (4, 0),
    (5, 20),
    (6, 50),
    (7,33),
    (8, 100)
ON DUPLICATE KEY UPDATE
    available_quantity = VALUES(available_quantity);