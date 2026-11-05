# Inventory gRPC Server (Node.js + MySQL)

This is the independent Inventory service required by the second assignment. It is the **gRPC server**; the Laravel Orders application is a PHP gRPC client.

> Why Node.js? The official gRPC PHP documentation says PHP currently supports creating gRPC clients only, and instructs users to create the server in another language. Therefore a Laravel 10 application cannot itself be presented as the official PHP gRPC server for this assignment. Node.js keeps the service genuinely polyglot and lets the existing Laravel code remain unchanged as the main application. References: https://grpc.io/docs/languages/php/quickstart/ and https://grpc.io/docs/languages/php/basics/.

## What this service owns

- `inventory_items`: the authoritative available quantity for each Catalog product ID.
- `stock_reservations`: one record per reservation reference, with `RESERVED` / `RELEASED` state.
- `stock_reservation_items`: the exact quantities held by each reservation.

It does not connect to or share the Orders database. `product_id` is an external identifier from the Catalog module; no foreign key crosses service databases.

## The three gRPC methods

The single contract is `proto/inventory.proto`:

1. `GetProductQuantity`: returns whether the product exists in Inventory and its current available quantity.
2. `ReserveStock`: receives **all** requested product IDs and quantities in one RPC. It checks and reserves the entire set atomically.
3. `ReleaseStock`: returns all quantities attached to one reservation reference. Repeated release calls are safe and do not add stock twice.

The same `.proto` file is also included in the Orders integration archive. Do not create two different contracts.

## Requirements

- Node.js 20 or later.
- MySQL 8.x (InnoDB).
- npm.

## Run with Docker Compose

From this folder, build and start the Inventory service and its private MySQL database:

```powershell
docker compose up --build -d
docker compose logs -f inventory
```

The gRPC service is published on `127.0.0.1:50051`. MySQL runs in its own container and is published on `127.0.0.1:3307` to avoid conflicting with the Orders project's MySQL port. The schema is applied automatically when the database volume is first created. The database is persisted in the `inventory-db-data` volume.

To stop the containers while keeping database data, run `docker compose down`. To remove the database data too, run `docker compose down -v`.

For local development, set `INVENTORY_DB_PASSWORD` and `INVENTORY_DB_ROOT_PASSWORD` in the shell or a `.env` file before starting Compose. The defaults are for local use only. The example seed is not loaded automatically; run it explicitly only in a development database.

## Run locally on Windows PowerShell

From this folder:

```powershell
Copy-Item .env.example .env
```

Edit `.env` with your MySQL credentials, then create the database and tables:

```powershell
cmd /c "mysql -u root -p < database\schema.sql"
cmd /c "mysql -u root -p inventory_db < database\seed.example.sql"
```

The example seed creates product IDs `1`, `2`, and `3`. Make sure these IDs also exist in your Catalog database. The seed script is for development only; do not run it against production because it intentionally sets sample quantities.

Install and start the server:

```powershell
npm install
npm start
```

Expected startup message:

```text
[inventory-grpc] Listening on 0.0.0.0:50051
```

Keep this process running while you test the Orders API. The default local endpoint is `127.0.0.1:50051`.

## Environment variables

| Variable | Default | Meaning |
|---|---:|---|
| `GRPC_HOST` | `0.0.0.0` | gRPC bind host |
| `GRPC_PORT` | `50051` | gRPC port |
| `DB_HOST` | `127.0.0.1` | MySQL host |
| `DB_PORT` | `3306` | MySQL port |
| `DB_NAME` | `inventory_db` | Private Inventory database |
| `DB_USER` | `root` | MySQL username |
| `DB_PASSWORD` | empty | MySQL password |
| `DB_CONNECTION_LIMIT` | `10` | MySQL connection pool limit |

## How race conditions are prevented

`ReserveStock` uses one InnoDB transaction:

1. Normalize duplicate product IDs and sort IDs in ascending order.
2. Lock every requested stock row using `SELECT ... FOR UPDATE`.
3. Check all products and all requested quantities before changing any row.
4. Decrement each row with an additional conditional update (`available_quantity >= requested`).
5. Store the reservation and its item rows, then commit once.

If even one product is missing or short of stock, the transaction rolls back and no product is decremented. Concurrent requests for the last unit serialize on the same row; after the first transaction commits, the next request sees zero and is rejected.

The unique `reservation_reference` and stored item hash make retries idempotent. `ReleaseStock` locks the reservation, adds its quantities back once, and changes its state to `RELEASED` in the same transaction. A repeated release sees `RELEASED` and does not add stock again. If the reference does not exist yet, `ReleaseStock` writes a `RELEASED` tombstone; this also prevents a late `ReserveStock` from completing after the client timed out and requested compensation.

## Manual compensation recovery

If Laravel logs a critical compensation error, copy the `reservation_reference` from the log after the Inventory server is healthy again, then run:

```powershell
npm run release -- <reservation-reference>
```

The operation is idempotent; it returns stock only if the reservation is still active. If the reference was never reserved, it stores a release tombstone to prevent a late reservation with the same reference.

## Direct gRPC race-condition test

First set a test product to one available unit (use an isolated development database):

```sql
UPDATE inventory_items SET available_quantity = 1 WHERE product_id = 3;
```

With the gRPC server still running, open another terminal in this folder and run:

```powershell
npm run test:race -- 20 3
```

Expected: exactly one reservation succeeds, the other 19 are rejected, and the test script releases its successful test reservation at the end so the test does not consume stock permanently.
