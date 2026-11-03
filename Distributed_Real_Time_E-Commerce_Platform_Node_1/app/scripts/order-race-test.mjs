// End-to-end test: many concurrent HTTP order requests competing for the last unit.
// Node.js 20+ is required. The Orders API must already be running.

const baseUrl = process.env.ORDERS_API_URL || 'http://127.0.0.1:8000/api/v1/orders';
const token = process.env.ORDERS_TOKEN;
const productId = Number(process.env.PRODUCT_ID || 3);
const concurrency = Number(process.env.CONCURRENCY || 10);

if (!token) {
  throw new Error('Set ORDERS_TOKEN to a valid customer Bearer token.');
}
if (!Number.isInteger(productId) || productId < 1 ||
    !Number.isInteger(concurrency) || concurrency < 2 || concurrency > 10) {
  throw new Error('PRODUCT_ID must be positive and CONCURRENCY must be between 2 and 10 (orders-create rate limit).');
}

async function placeOne(index) {
  try {
    const response = await fetch(baseUrl, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ items: [{ product_id: productId, quantity: 1 }] }),
    });

    const body = await response.json().catch(() => ({}));
    return { index, status: response.status, body };
  } catch (error) {
    return { index, status: 0, body: { message: error.message } };
  }
}

const results = await Promise.all(
  Array.from({ length: concurrency }, (_, index) => placeOne(index + 1)),
);

const created = results.filter((result) => result.status === 201).length;
const insufficient = results.filter((result) => result.status === 409).length;
const unavailable = results.filter((result) => result.status === 503).length;

console.log(`Requests: ${results.length}`);
console.log(`Created (201): ${created}`);
console.log(`Insufficient stock (409): ${insufficient}`);
console.log(`Inventory unavailable (503): ${unavailable}`);
for (const result of results) {
  console.log(`#${result.index} HTTP ${result.status}: ${result.body.message || JSON.stringify(result.body)}`);
}

if (created === 1 && insufficient === concurrency - 1) {
  console.log('PASS: exactly one order was created for the last unit.');
} else {
  console.error('FAIL: expected exactly one 201 and all other requests to receive 409.');
  process.exitCode = 1;
}
