const crypto = require('node:crypto');

class InventoryRepository {
  constructor(pool) {
    this.pool = pool;
  }

  async getProductQuantity(productId) {
    const [rows] = await this.pool.execute(
      'SELECT available_quantity FROM inventory_items WHERE product_id = ? LIMIT 1',
      [productId],
    );

    if (rows.length === 0) {
      return { found: false, available_quantity: 0 };
    }

    return {
      found: true,
      available_quantity: Number(rows[0].available_quantity),
    };
  }

  async reserveStock(reference, inputItems) {
  
    if (!reference || reference.length > 64 || inputItems.length === 0) {
      return { success: false, message: 'A valid reservation reference and at least one item are required.' };
    }

    const itemsHash = createItemsHash(inputItems);
    const connection = await this.pool.getConnection();

    try {
      await connection.query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
      await connection.beginTransaction();

      // Same reference + same payload: safe retry after a lost response.
      const [existing] = await connection.execute(
        'SELECT items_hash, status FROM stock_reservations WHERE reservation_reference = ? FOR UPDATE',
        [reference],
      );

      if (existing.length > 0) {
        await connection.rollback();
        const row = existing[0];
        if (row.items_hash === itemsHash && row.status === 'RESERVED') {
          return { success: true, message: 'This reservation was already completed.' };
        }
        return { success: false, message: 'The reservation reference was already used with a different or released reservation.' };
      }

      const ids = inputItems.map((item) => item.product_id);
      const placeholders = ids.map(() => '?').join(',');

      // Always lock in ascending product-id order to reduce deadlock risk.
      const [stockRows] = await connection.query(
        `SELECT product_id, available_quantity
           FROM inventory_items
          WHERE product_id IN (${placeholders})
          ORDER BY product_id
          FOR UPDATE`,
        ids,
      );

      const stockById = new Map(
        stockRows.map((row) => [String(row.product_id), Number(row.available_quantity)]),
      );

      for (const item of inputItems) {
        const available = stockById.get(String(item.product_id));
        if (available === undefined) {
          await connection.rollback();
          return {
            success: false,
            message: `Product ${item.product_id} does not exist in the inventory service.`,
          };
        }
        if (available < item.quantity) {
          await connection.rollback();
          return {
            success: false,
            message: `Product ${item.product_id} has only ${available} item(s) available; ${item.quantity} requested.`,
          };
        }
      }

      await connection.execute(
        `INSERT INTO stock_reservations (reservation_reference, items_hash, status)
         VALUES (?, ?, 'RESERVED')`,
        [reference, itemsHash],
      );

      for (const item of inputItems) {
        // Conditional update is a second safety barrier in addition to FOR UPDATE.
        const [result] = await connection.execute(
          `UPDATE inventory_items
              SET available_quantity = available_quantity - ?
            WHERE product_id = ? AND available_quantity >= ?`,
          [item.quantity, item.product_id, item.quantity],
        );

        if (result.affectedRows !== 1) {
          throw new Error(`Concurrent stock update detected for product ${item.product_id}.`);
        }

        await connection.execute(
          `INSERT INTO stock_reservation_items (reservation_reference, product_id, quantity)
           VALUES (?, ?, ?)`,
          [reference, item.product_id, item.quantity],
        );
      }

      await connection.commit();
      return { success: true, message: 'Stock reserved successfully.' };
    } catch (error) {
      await safeRollback(connection);
      if (error && error.code === 'ER_DUP_ENTRY') {
        return { success: false, message: 'This reservation reference was already used or cancelled.' };
      }
      throw error;
    } finally {
      connection.release();
    }
  }

  async releaseStock(reference) {
    if (!reference || reference.length > 64) {
      return { success: false, message: 'A valid reservation reference is required.' };
    }

    const connection = await this.pool.getConnection();

    try {
      await connection.query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
      await connection.beginTransaction();
      const [reservations] = await connection.execute(
        'SELECT status FROM stock_reservations WHERE reservation_reference = ? FOR UPDATE',
        [reference],
      );

      if (reservations.length === 0) {
        // Create a RELEASED tombstone. This closes the timeout race where a
        // ReserveStock operation is still running after the caller's deadline:
        // a late reserve with this reference can no longer decrement stock.
        // INSERT IGNORE waits if a concurrent reserve is inserting the same key.
        await connection.execute(
          `INSERT IGNORE INTO stock_reservations
             (reservation_reference, items_hash, status, released_at)
           VALUES (?, NULL, 'RELEASED', CURRENT_TIMESTAMP)`,
          [reference],
        );

        const [afterInsert] = await connection.execute(
          'SELECT status FROM stock_reservations WHERE reservation_reference = ? FOR UPDATE',
          [reference],
        );

        if (afterInsert.length === 0) {
          throw new Error(`Could not record release marker for ${reference}.`);
        }

        if (afterInsert[0].status === 'RELEASED') {
          await connection.commit();
          return { success: true, message: 'No active reservation existed; a release marker was saved.' };
        }
      }

      if (reservations.length > 0 && reservations[0].status === 'RELEASED') {
        await connection.rollback();
        return { success: true, message: 'This reservation was already released.' };
      }

      const [items] = await connection.execute(
        `SELECT product_id, quantity
           FROM stock_reservation_items
          WHERE reservation_reference = ?
          ORDER BY product_id
          FOR UPDATE`,
        [reference],
      );

      for (const item of items) {
        const [result] = await connection.execute(
          `UPDATE inventory_items
              SET available_quantity = available_quantity + ?
            WHERE product_id = ?`,
          [item.quantity, item.product_id],
        );

        if (result.affectedRows !== 1) {
          throw new Error(`Inventory row ${item.product_id} is missing during release.`);
        }
      }

      await connection.execute(
        `UPDATE stock_reservations
            SET status = 'RELEASED', released_at = CURRENT_TIMESTAMP
          WHERE reservation_reference = ?`,
        [reference],
      );

      await connection.commit();
      return { success: true, message: 'Reserved quantities returned to inventory.' };
    } catch (error) {
      await safeRollback(connection);
      throw error;
    } finally {
      connection.release();
    }
  }
}


function createItemsHash(items) {
  return crypto.createHash('sha256').update(JSON.stringify(items)).digest('hex');
}

async function safeRollback(connection) {
  try {
    await connection.rollback();
  } catch (_) {
    // Preserve the original database error.
  }
}

module.exports = { InventoryRepository };
