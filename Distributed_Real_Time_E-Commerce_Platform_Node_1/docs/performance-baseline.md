# Performance baseline: products and orders

This is the first, unoptimized measurement only. The existing production product/order routes and Docker files are not changed. The benchmark routes use ordinary offset pagination, retrieve full model rows, and lazy-load relationships. List requests therefore expose the baseline N+1 behavior.

## Separate local database and routes

Use a dedicated local MySQL database named `ecommerce_performance`; never point this setup at the production database. Copy `.env.performance.example` to `.env.performance` and set credentials for that database. Keep `.env.performance` local and do not commit it.

The four benchmark-only routes are registered only when `PERFORMANCE_TESTING=true`, and the application refuses to register them in a production environment:

| Request | Purpose |
|---|---|
| `GET /api/performance/baseline/products?per_page=20` | Product listing; ordinary pagination and lazy category/image reads |
| `GET /api/performance/baseline/products/{id}` | Product detail; lazy category/image reads |
| `GET /api/performance/baseline/orders?per_page=15` | Order listing; ordinary pagination and lazy item/product reads |
| `GET /api/performance/baseline/orders/{id}` | Order detail; lazy item/product reads |

The endpoints are unauthenticated because the generated records are synthetic. They do not appear in the normal application unless the performance environment is explicitly selected.

## Commands to run locally

These are instructions for the project owner to run; this setup does not execute them:

1. Copy `.env.performance.example` to `.env.performance`.
2. Create the dedicated MySQL database and user named in that file. Ensure the user has rights only to `ecommerce_performance`.
3. Clear any previously cached application configuration, then generate an application key for this environment:

   ```powershell
   php artisan config:clear --env=performance
   php artisan key:generate --env=performance
   ```

4. Create only the performance database schema and seed the large dataset:

   ```powershell
   php artisan migrate:fresh --env=performance --force
   php artisan db:seed --class=PerformanceSeeder --env=performance --force
   ```

   The seeder creates 5,000 products, 10,000 images, 100,000 orders, and 400,000 order items. Generation can take several minutes.

5. Remove non-unique query indexes while retaining primary keys, unique indexes, all foreign-key constraints, and the minimal indexes needed to enforce those constraints:

   ```powershell
   php artisan performance:baseline:drop-indexes --env=performance
   ```

   If a migration/schema update later adds a non-unique index not needed by a foreign key, this command removes it. Where an existing composite index is needed only to support a foreign key, the command replaces it with the minimal foreign-key-column index instead of dropping the constraint.

6. Start the isolated local API process:

   ```powershell
   php artisan serve --env=performance --host=127.0.0.1 --port=8081
   ```

The separate environment file, database name, and local-only route flag keep the benchmark apart from the production endpoints and database. Do not copy production credentials into `.env.performance`.

## Run k6

Install k6 on the host and verify it is available in PowerShell:

```powershell
k6 version
```

From the Laravel project directory, run the controlled baseline:

```powershell
k6 run --vus 10 --duration 60s --summary-trend-stats "avg,min,max,med,p(50),p(95),p(99)" --summary-export "baseline-summary.json" .\performance\k6\baseline.js
```

The script sends requests to all four endpoints in each iteration and checks that they return HTTP 200. It performs no writes. The summary is printed and saved to `baseline-summary.json`; record the `http_req_duration` percentiles and failure rate. For a smoke test, use `--vus 1 --duration 10s`. Increase load only after checking that the host and database remain responsive.

For a future after-optimization comparison, preserve the same dataset and machine, then run an equivalent k6 scenario against the optimized test-only endpoints. Do not compare runs with different VU counts, durations, data, or resource limits.

## Baseline EXPLAIN

After seeding and removing the non-unique query indexes, run these read-only statements against `ecommerce_performance` and save their plans:

```sql
EXPLAIN SELECT * FROM products
WHERE is_active = 1
ORDER BY id DESC
LIMIT 20 OFFSET 0;

EXPLAIN SELECT * FROM orders
ORDER BY id DESC
LIMIT 15 OFFSET 0;
```

Capture the actual plans and k6 output before implementing optimizations. This setup does not invent before/after metrics; the numbers must come from the target environment.
