<?php

namespace App\Console\Commands;

use App\Http\Controllers\Performance\EloquentOptimizedQueries;
use App\Modules\Catalog\Domain\Entities\Product;
use App\Modules\Orders\Domain\Entities\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PerformanceQueryReport extends Command
{
    protected $signature = 'performance:report
        {phase : before (drops indexes, measures baseline) or after (restores indexes + migrate, measures optimized)}
        {--show : print the SQL of every query}
        {--no-index-change : do not drop/restore indexes, measure the current state as is}';

    protected $description = 'Prepare the index state for a phase, then print query counts and EXPLAIN ANALYZE results.';

    public function handle(): int
    {
        if (!config('app.performance_testing') || app()->environment('production')) {
            $this->error('Enable PERFORMANCE_TESTING outside production before running this command.');

            return self::FAILURE;
        }

        $phase = $this->argument('phase');

        if (!in_array($phase, ['before', 'after'], true)) {
            $this->error('Phase must be "before" or "after".');

            return self::FAILURE;
        }

        if (!$this->option('no-index-change')) {
            if ($phase === 'before') {
                $this->info('[1/3] Dropping secondary indexes (snapshot saved)...');
                if ($this->call('performance:baseline:drop-indexes') !== self::SUCCESS) {
                    return self::FAILURE;
                }
            } else {
                $this->info('[1/3] Restoring indexes and running migrations...');
                if ($this->call('performance:restore-indexes') !== self::SUCCESS) {
                    return self::FAILURE;
                }
                if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS) {
                    return self::FAILURE;
                }
            }
        }

        $this->newLine();
        $this->info("[2/3] Query counts ({$phase})");
        $this->countQueries($phase);

        $this->newLine();
        $this->info("[3/3] EXPLAIN ANALYZE ({$phase})");
        $this->explainQueries($phase);

        return self::SUCCESS;
    }

    // ---------- عدّ الاستعلامات ----------

    private function countQueries(string $phase): void
    {
        if ($phase === 'before') {
            $scenarios = [
                'orders   | baseline' => function () {
                    $orders = Order::query()->latest('id')->paginate(15);
                    foreach ($orders as $order) {
                        foreach ($order->items as $item) {
                            $item->product?->name;
                        }
                    }
                },
                'products | baseline' => function () {
                    $products = Product::query()->where('is_active', true)->orderByDesc('id')->paginate(20);
                    foreach ($products as $product) {
                        $product->category?->name;
                        $product->images->count();
                    }
                },
            ];
        } else {
            $optimized = new EloquentOptimizedQueries();

            $scenarios = [
                'orders   | optimized' => function () use ($optimized) {
                    $orders = $optimized->orders(15, null, request());
                    foreach ($orders as $order) {
                        foreach ($order->items as $item) {
                            $item->product?->name;
                        }
                    }
                },
                'products | optimized' => function () use ($optimized) {
                    $products = $optimized->products(20, null, request());
                    foreach ($products as $product) {
                        $product->category?->name;
                        $product->images->count();
                    }
                },
            ];
        }

        $rows = [];

        foreach ($scenarios as $name => $run) {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $start = microtime(true);
            $run();
            $ms = (microtime(true) - $start) * 1000;

            $log = DB::getQueryLog();
            DB::disableQueryLog();

            $rows[] = [$name, count($log), number_format($ms, 1) . ' ms'];

            if ($this->option('show')) {
                $this->line("--- {$name} ---");
                foreach ($log as $query) {
                    $this->line($query['query']);
                }
            }
        }

        $this->table(['Scenario', 'Queries', 'Time'], $rows);
    }

    // ---------- EXPLAIN ANALYZE ----------

    private function explainQueries(string $phase): void
    {
        $total = (int) DB::table('orders')->count();
        $offset = $total > 150015 ? 150000 : max(0, $total - 20);
        $cursorId = (int) DB::table('orders')->orderByDesc('id')->offset($offset)->limit(1)->value('id');
        $sampleOrderId = (int) DB::table('orders')->orderByDesc('id')->value('id');

        $this->line("orders total={$total} | deep offset={$offset} | cursor id={$cursorId}");

        if ($phase === 'before') {
            $queries = [
                'orders: COUNT(*) (paginate)' =>
                    'SELECT COUNT(*) FROM orders',
                "orders: OFFSET {$offset}" =>
                    "SELECT * FROM orders ORDER BY id DESC LIMIT 15 OFFSET {$offset}",
                'order_items: single order (N+1)' =>
                    "SELECT * FROM order_items WHERE order_id = {$sampleOrderId}",
                'products: list active' =>
                    'SELECT id, category_id, name, slug, price, is_active, created_at, updated_at FROM products WHERE is_active = 1 ORDER BY id DESC LIMIT 21',
            ];
        } else {
            $queries = [
                'orders: cursor WHERE id < ?' =>
                    "SELECT id, user_id, status, total_amount, created_at, updated_at FROM orders WHERE id < {$cursorId} ORDER BY id DESC LIMIT 16",
                'order_items: IN (...)' =>
                    "SELECT id, order_id, product_id, quantity, unit_price FROM order_items WHERE order_id IN ({$sampleOrderId}, " . ($sampleOrderId - 1) . ', ' . ($sampleOrderId - 2) . ') ORDER BY id',
                'products: list active' =>
                    'SELECT id, category_id, name, slug, price, is_active, created_at, updated_at FROM products WHERE is_active = 1 ORDER BY id DESC LIMIT 21',
            ];
        }

        foreach ($queries as $label => $sql) {
            $this->newLine();
            $this->line("=== {$label} ===");
            $this->line($sql);

            try {
                foreach (DB::select('EXPLAIN ANALYZE ' . $sql) as $row) {
                    foreach ((array) $row as $line) {
                        $this->line((string) $line);
                    }
                }
            } catch (\Throwable $e) {
                $this->error('EXPLAIN ANALYZE failed: ' . $e->getMessage());
            }
        }
    }
}
