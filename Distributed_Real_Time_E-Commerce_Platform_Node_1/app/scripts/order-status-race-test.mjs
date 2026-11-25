// Node.js 20+. Run from the Node_1 project root while the API and both databases are running.
// PowerShell:
// $env:BASE_URL='http://127.0.0.1:8080/api/v1'; $env:TOKEN='...'
// $env:ORDER_ID='12'; $env:STATUSES='Cancelled,Cancelled'
// node app/scripts/order-status-race-test.mjs

import { spawnSync } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const baseUrl = (process.env.BASE_URL || 'http://127.0.0.1:8000/api/v1').replace(/\/+$/, '');
const token = process.env.TOKEN;
const orderId = Number(process.env.ORDER_ID);
const statuses = String(process.env.STATUSES || 'Cancelled,Cancelled')
  .split(',')
  .map((status) => status.trim())
  .filter(Boolean);
const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../..');

if (!token) throw new Error('Set TOKEN to a valid bearer token.');
if (!Number.isSafeInteger(orderId) || orderId < 1) throw new Error('ORDER_ID must be positive.');
if (statuses.length < 2) throw new Error('STATUSES must contain at least two statuses.');

function runPhp(code) {
  const result = spawnSync(process.env.PHP_BINARY || 'php', ['-r', code], {
    cwd: projectRoot,
    env: process.env,
    encoding: 'utf8',
  });
  if (result.error) throw result.error;
  if (result.status !== 0) {
    throw new Error(`Database verification failed: ${result.stderr || result.stdout}`);
  }
  try {
    return JSON.parse(result.stdout.trim());
  } catch {
    throw new Error(`Database verification returned invalid JSON: ${result.stdout || result.stderr}`);
  }
}

function orderAudit() {
  const code = [
    "require 'vendor/autoload.php';",
    "$app = require 'bootstrap/app.php';",
    "$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();",
    `$id = ${orderId};`,
    "$order = Illuminate\\Support\\Facades\\DB::table('orders')->useWritePdo()->select('status', 'inventory_reservation_reference')->where('id', $id)->first();",
    "if (! $order) { fwrite(STDERR, 'Order not found.'); exit(2); }",
    "$count = Illuminate\\Support\\Facades\\DB::table('order_status_outbox')->useWritePdo()->where('order_id', $id)->count();",
    "echo json_encode(['status' => $order->status, 'reference' => $order->inventory_reservation_reference, 'outbox' => $count], JSON_THROW_ON_ERROR);",
  ].join(' ');
  return runPhp(code);
}

function inventoryAudit(reference) {
  if (!reference) throw new Error('Order has no inventory reservation reference.');
  const env = (key, fallback) => process.env[key] || fallback;
  const b64 = (value) => Buffer.from(value).toString('base64');
  const host = env('INVENTORY_DB_HOST', '127.0.0.1');
  const port = Number(env('INVENTORY_DB_PORT', '3307'));
  const database = env('INVENTORY_DB_NAME', 'distributed_real_time_e_commerce_platform_node_2');
  const username = env('INVENTORY_DB_USER', 'inventory');
  const password = env('INVENTORY_DB_PASSWORD', 'inventory-local-pass');
  const code = [
    `$host = base64_decode('${b64(host)}');`,
    `$database = base64_decode('${b64(database)}');`,
    `$username = base64_decode('${b64(username)}');`,
    `$password = base64_decode('${b64(password)}');`,
    `$reference = base64_decode('${b64(reference)}');`,
    `$pdo = new PDO("mysql:host={$host};port=${port};dbname={$database};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);`,
    "$statement = $pdo->prepare('SELECT r.status, r.released_at, i.product_id, i.quantity, s.available_quantity FROM stock_reservations r LEFT JOIN stock_reservation_items i ON i.reservation_reference = r.reservation_reference LEFT JOIN inventory_items s ON s.product_id = i.product_id WHERE r.reservation_reference = ? ORDER BY i.product_id');",
    '$statement->execute([$reference]);',
    '$rows = $statement->fetchAll(PDO::FETCH_ASSOC);',
    "if (! $rows) { fwrite(STDERR, 'Inventory reservation not found.'); exit(2); }",
    "$items = array_map(fn ($row) => ['product_id' => (int) $row['product_id'], 'quantity' => (int) $row['quantity'], 'available_quantity' => (int) $row['available_quantity']], array_filter($rows, fn ($row) => $row['product_id'] !== null));",
    "echo json_encode(['status' => $rows[0]['status'], 'released_at' => $rows[0]['released_at'], 'items' => $items], JSON_THROW_ON_ERROR);",
  ].join(' ');
  return runPhp(code);
}

function sameStock(left, right) {
  return JSON.stringify(left.items) === JSON.stringify(right.items);
}

async function patchStatus(status, index) {
  try {
    const response = await fetch(`${baseUrl}/orders/${orderId}/status`, {
      method: 'PATCH',
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ status }),
    });
    const body = await response.json().catch(() => ({}));
    return {
      index,
      requested: status,
      httpStatus: response.status,
      finalStatus: body?.data?.status ?? null,
      message: body?.message ?? JSON.stringify(body),
    };
  } catch (error) {
    return { index, requested: status, httpStatus: 0, finalStatus: null, message: error.message };
  }
}

const beforeOrder = orderAudit();
const beforeInventory = inventoryAudit(beforeOrder.reference);
if (beforeOrder.status === 'Cancelled' || beforeInventory.status !== 'RESERVED') {
  throw new Error('Use an order that is not cancelled and has a RESERVED inventory reservation.');
}

const results = await Promise.all(statuses.map((status, index) => patchStatus(status, index + 1)));
const successes = results.filter((result) => result.httpStatus === 200);
const conflicts = results.filter((result) => result.httpStatus === 409);
const afterRaceOrder = orderAudit();
const afterRaceInventory = inventoryAudit(beforeOrder.reference);
const outboxDelta = afterRaceOrder.outbox - beforeOrder.outbox;
let repeatStatus = null;
let repeatOutboxDelta = null;
let inventoryStableOnRepeat = null;

if (successes.length === 1 && successes[0].finalStatus === 'Cancelled') {
  repeatStatus = (await patchStatus('Cancelled', statuses.length + 1)).httpStatus;
  const repeatedOrder = orderAudit();
  const repeatedInventory = inventoryAudit(beforeOrder.reference);
  repeatOutboxDelta = repeatedOrder.outbox - afterRaceOrder.outbox;
  inventoryStableOnRepeat = repeatedInventory.status === afterRaceInventory.status &&
    repeatedInventory.released_at === afterRaceInventory.released_at &&
    sameStock(afterRaceInventory, repeatedInventory);
}

console.log(`Concurrent requests: ${results.length}`);
console.log(`HTTP 200 successes: ${successes.length}`);
console.log(`HTTP 409 conflicts: ${conflicts.length}`);
console.log(`Outbox rows added by race: ${outboxDelta}`);
for (const result of results) {
  console.log(`#${result.index} ${result.requested} -> HTTP ${result.httpStatus}: ${result.message}`);
}

if (afterRaceOrder.status === 'Cancelled') {
  const restoredExactlyOnce = afterRaceInventory.status === 'RELEASED' &&
    JSON.stringify(afterRaceInventory.items) === JSON.stringify(beforeInventory.items.map((item) => ({
      ...item,
      available_quantity: item.available_quantity + item.quantity,
    })));
  console.log(`Inventory release restored quantities exactly once: ${restoredExactlyOnce ? 'YES' : 'NO'}`);
  console.log(`Second Cancelled request: HTTP ${repeatStatus}`);
  console.log(`Outbox rows added by second cancel: ${repeatOutboxDelta}`);
  console.log(`Inventory unchanged by second cancel: ${inventoryStableOnRepeat ? 'YES' : 'NO'}`);

  if (successes.length !== 1 || conflicts.length !== results.length - 1 ||
      outboxDelta !== 1 || !restoredExactlyOnce || repeatStatus !== 409 ||
      repeatOutboxDelta !== 0 || !inventoryStableOnRepeat) {
    console.error('FAIL: status race, outbox, or inventory release verification failed.');
    process.exitCode = 1;
  } else {
    console.log('PASS: one transition/outbox row and one release; repeat cancellation conflicted.');
  }
} else {
  console.log('No inventory release was expected because the winning transition was not Cancelled.');
  if (successes.length !== 1 || conflicts.length !== results.length - 1 ||
      outboxDelta !== 1 || !sameStock(beforeInventory, afterRaceInventory)) {
    console.error('FAIL: status race, outbox, or no-release verification failed.');
    process.exitCode = 1;
  } else {
    console.log('PASS: one transition/outbox row; inventory was not released.');
  }
}
