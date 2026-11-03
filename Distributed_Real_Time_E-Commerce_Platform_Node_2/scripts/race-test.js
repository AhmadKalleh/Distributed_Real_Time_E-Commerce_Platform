require('dotenv').config();

const path = require('node:path');
const crypto = require('node:crypto');
const grpc = require('@grpc/grpc-js');
const protoLoader = require('@grpc/proto-loader');

const [,, concurrencyArg = '20', productIdArg = '3'] = process.argv;
const concurrency = Number(concurrencyArg);
const productId = Number(productIdArg);

if (!Number.isInteger(concurrency) || concurrency < 2 ||
    !Number.isInteger(productId) || productId < 1) {
  throw new Error('Usage: npm run test:race -- <concurrency>=20 <productId>=3');
}

const definition = protoLoader.loadSync(
  path.join(__dirname, '..', 'proto', 'inventory.proto'),
  { keepCase: true, longs: Number, enums: String, defaults: true, oneofs: true },
);
const inventory = grpc.loadPackageDefinition(definition).inventory.v1;
const target = `${process.env.GRPC_CLIENT_HOST || '127.0.0.1'}:${process.env.GRPC_PORT || 50051}`;
const client = new inventory.InventoryService(
  target,
  grpc.credentials.createInsecure(),
);

function reserveOne() {
  const reference = crypto.randomUUID();
  return new Promise((resolve) => {
    client.reserveStock(
      {
        reservation_reference: reference,
        items: [{ product_id: productId, quantity: 1 }],
      },
      new grpc.Metadata(),
      { deadline: Date.now() + 5000 },
      (error, response) => {
        if (error) return resolve({ success: false, reference, error: error.message });
        resolve({ success: Boolean(response.success), reference, message: response.message });
      },
    );
  });
}

(async () => {
  const results = await Promise.all(
    Array.from({ length: concurrency }, () => reserveOne()),
  );
  const winners = results.filter((result) => result.success);
  const successful = winners.length;
  const rejected = results.length - successful;

  process.stdout.write(`Requests: ${results.length}\n`);
  process.stdout.write(`Successful reservations: ${successful}\n`);
  process.stdout.write(`Rejected reservations: ${rejected}\n`);
  results.forEach((result, index) => {
    process.stdout.write(`${index + 1}. ${result.success ? 'SUCCESS' : 'REJECTED'} - ${result.message || result.error}\n`);
  });

  // This low-level test must not consume real stock permanently. Release every
  // successful test reservation, even if an unexpected bug allowed more than one.
  for (const winner of winners) {
    await new Promise((resolve) => client.releaseStock(
      { reservation_reference: winner.reference },
      new grpc.Metadata(),
      { deadline: Date.now() + 5000 },
      (error, response) => {
        if (error || !response.success) {
          console.error(`Could not release test reservation ${winner.reference}: ${error?.message || response?.message}`);
          process.exitCode = 1;
        } else {
          console.log(`Test reservation ${winner.reference} released.`);
        }
        resolve();
      },
    ));
  }

  client.close();
  if (successful !== 1) {
    process.stderr.write('FAIL: expected exactly one successful reservation.\n');
    process.exitCode = 1;
  } else {
    process.stdout.write('PASS: race condition was handled; exactly one request reserved the last unit.\n');
  }
})();
