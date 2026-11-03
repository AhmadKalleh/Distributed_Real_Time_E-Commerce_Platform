require('dotenv').config();

const path = require('node:path');
const grpc = require('@grpc/grpc-js');
const protoLoader = require('@grpc/proto-loader');
const { createPool } = require('./database');
const { InventoryRepository } = require('./inventory-repository');
const { createInventoryHandlers } = require('./inventory-service');

async function main() {
  const pool = createPool();
  await pool.query('SELECT 1');

  const protoPath = path.join(__dirname, '..', 'proto', 'inventory.proto');
  const packageDefinition = protoLoader.loadSync(protoPath, {
    keepCase: true,
    longs: Number,
    enums: String,
    defaults: true,
    oneofs: true,
  });

  const inventoryProto = grpc.loadPackageDefinition(packageDefinition).inventory.v1;
  const repository = new InventoryRepository(pool);
  const server = new grpc.Server();

  server.addService(
    inventoryProto.InventoryService.service,
    createInventoryHandlers(repository),
  );

  const host = process.env.GRPC_HOST || '0.0.0.0';
  const port = Number(process.env.GRPC_PORT || 50051);
  const address = `${host}:${port}`;

  server.bindAsync(address, grpc.ServerCredentials.createInsecure(), (error, boundPort) => {
    if (error) {
      process.stderr.write(`[inventory-grpc] Could not bind ${address}: ${error.message}\n`);
      process.exitCode = 1;
      return;
    }

    server.start();
    process.stdout.write(`[inventory-grpc] Listening on ${host}:${boundPort}\n`);
  });

  const shutdown = () => {
    process.stdout.write('[inventory-grpc] Shutting down...\n');
    server.tryShutdown(async () => {
      await pool.end();
      process.exit(0);
    });
  };

  process.once('SIGINT', shutdown);
  process.once('SIGTERM', shutdown);
}

main().catch((error) => {
  process.stderr.write(`[inventory-grpc] Startup failed: ${error.stack || error}\n`);
  process.exit(1);
});
