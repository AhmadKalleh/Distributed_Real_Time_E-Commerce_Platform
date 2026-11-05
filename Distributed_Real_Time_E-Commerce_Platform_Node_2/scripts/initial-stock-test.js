const grpc = require('@grpc/grpc-js');
const protoLoader = require('@grpc/proto-loader');
const path = require('node:path');
const { createPool } = require('../src/database');

const definition = protoLoader.loadSync(
  path.join(__dirname, '..', 'proto', 'inventory.proto'),
  { keepCase: true, longs: Number, enums: String, defaults: true, oneofs: true },
);
const InventoryService = grpc.loadPackageDefinition(definition).inventory.v1.InventoryService;
const client = new InventoryService(
  '127.0.0.1:50051',
  grpc.credentials.createInsecure(),
);
const pool = createPool();
const productId = Number(`${Date.now()}${Math.floor(Math.random() * 1000).toString().padStart(3, '0')}`);

function call(method, request) {
  return new Promise((resolve, reject) => {
    client[method](request, (error, response) => {
      if (error) reject(error);
      else resolve(response);
    });
  });
}

async function main() {
  try {
    const initialized = await call('InitializeProductStock', {
      product_id: productId,
      quantity: 8,
    });
    const quantity = await call('GetProductQuantity', { product_id: productId });
    const duplicate = await call('InitializeProductStock', {
      product_id: productId,
      quantity: 99,
    });

    if (!initialized.success || !quantity.found || quantity.available_quantity !== 8 || duplicate.success) {
      throw new Error('Initial stock RPC returned unexpected results.');
    }

    process.stdout.write('PASS: initial quantity is stored, readable, and cannot be overwritten.\n');
  } finally {
    await pool.execute('DELETE FROM inventory_items WHERE product_id = ?', [productId]);
    await pool.end();
    client.close();
  }
}

main().catch((error) => {
  process.stderr.write(`FAIL: ${error.message}\n`);
  process.exitCode = 1;
});