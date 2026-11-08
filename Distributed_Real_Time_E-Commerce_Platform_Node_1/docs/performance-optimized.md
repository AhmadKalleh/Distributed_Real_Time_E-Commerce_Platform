# Performance tests: after optimization

This phase adds isolated optimized benchmark routes without changing the existing product or order production routes. The baseline routes, k6 script, and documentation are unchanged.

## What is compared

| Route pattern | Implementation |
|---|---|
| `/api/performance/optimized/eloquent/products` | Eloquent projection, eager category/images loading, and Laravel `cursorPaginate` |
| `/api/performance/optimized/eloquent/orders` | Eloquent projection, eager order-items/products loading, and Laravel `cursorPaginate` |
| `/api/performance/optimized/sql/products` | Parameterized SQL row queries, category join, batch image load, and cursor-based keyset pagination |
| `/api/performance/optimized/sql/orders` | Parameterized SQL row queries, batch item/product join, and cursor-based keyset pagination |

Both implementations also provide product and order detail routes. The optimized product listing selects only fields needed for a listing; product details include the description. Related rows are loaded in a fixed number of queries per page/detail rather than one query per record.

The Eloquent version typically executes one parent query plus eager-load queries for its related models. The raw SQL version executes one parent query and one batch query for related data. The query count is bounded and does not increase with the number of records on the page.

## Restore the baseline query indexes

The baseline index-drop command saves the removed index names and columns to `storage/app/performance-indexes-snapshot.json`. After recording baseline k6 and EXPLAIN results, restore those indexes on the same isolated performance database:

```powershell
php artisan performance:restore-indexes --env=performance
```

The command restores the indexes declared by the current migrations and any additional indexes captured by the baseline snapshot. It also removes temporary `perf_fk_*` support indexes when a restored index now supports the same foreign key. It does not modify primary keys, unique indexes, or foreign keys. If there is no snapshot, it restores the declared migration indexes. Re-running it is safe: matching indexes are reported as already present.

## Start the isolated API and compare engines

Use the same `.env.performance` environment and isolated database described in [performance-baseline.md](./performance-baseline.md). Start the local server with performance routes enabled:

```powershell
php artisan serve --env=performance --host=127.0.0.1 --port=8000
```

Run the optimized scenario once per query implementation. Keep the VU count, duration, host, and dataset the same as the baseline run:

```powershell
$env:ENGINE = "eloquent"
$env:LABEL = "after-eloquent"
k6 run --vus 10 --duration 60s .\performance\k6\optimized.js

$env:ENGINE = "sql"
$env:LABEL = "after-sql"
k6 run --vus 10 --duration 60s .\performance\k6\optimized.js
```

Each run saves a labeled JSON summary and prints p50, p95, and p99 for product listing/details and order listing/details. By default, each iteration sends the same four requests as the baseline. To additionally exercise cursor continuation, set `$env:FOLLOW_CURSORS = "true"` for both optimized runs; this adds next-page requests, so compare those two runs with each other rather than directly with the baseline request mix.

The routes require `PERFORMANCE_TESTING=true` and return 404 outside non-production test environments. They are unauthenticated because the dataset is synthetic. Do not enable the performance environment against a production database.

## EXPLAIN after restoring indexes

Capture plans from the isolated database after restoration. These match the optimized projection and keyset pagination query shapes:

```sql
EXPLAIN SELECT id, category_id, name, slug, price, is_active, created_at, updated_at
FROM products
WHERE is_active = 1 AND id < 5000
ORDER BY id DESC
LIMIT 21;

EXPLAIN SELECT id, user_id, status, total_amount, created_at, updated_at
FROM orders
WHERE id < 100000
ORDER BY id DESC
LIMIT 16;
```

Save the returned plans and k6 summaries alongside the baseline results. Report actual measurements from the test machine; do not infer performance gains from the query shape alone.
