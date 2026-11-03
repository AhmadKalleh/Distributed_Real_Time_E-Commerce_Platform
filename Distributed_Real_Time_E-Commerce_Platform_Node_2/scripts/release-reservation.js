require('dotenv').config();

const path = require('node:path');
const grpc = require('@grpc/grpc-js');
const protoLoader = require('@grpc/proto-loader');

const reference = process.argv[2];
if (!reference) {
  throw new Error('Usage: node scripts/release-reservation.js <reservation-reference>');
}

const definition = protoLoader.loadSync(
  path.join(__dirname, '..', 'proto', 'inventory.proto'),
  { keepCase: true, longs: Number, enums: String, defaults: true, oneofs: true },
);
const inventory = grpc.loadPackageDefinition(definition).inventory.v1;
const target = `${process.env.GRPC_CLIENT_HOST || '127.0.0.1'}:${process.env.GRPC_PORT || 50051}`;
const client = new inventory.InventoryService(target, grpc.credentials.createInsecure());

client.releaseStock(
  { reservation_reference: reference },
  new grpc.Metadata(),
  { deadline: Date.now() + 5000 },
  (error, response) => {
    if (error) {
      console.error(`Release RPC failed: ${error.message}`);
      process.exitCode = 1;
    } else if (!response.success) {
      console.error(`Release rejected: ${response.message}`);
      process.exitCode = 1;
    } else {
      console.log(response.message);
    }
    client.close();
  },
);
