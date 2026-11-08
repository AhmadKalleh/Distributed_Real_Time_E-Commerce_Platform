<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as FakerFactory;
use RuntimeException;

class PerformanceSeeder extends Seeder
{
    private const USERS_COUNT = 1000;
    private const RESPONSIBLE_COUNT = 50;
    private const CATEGORIES_COUNT = 50;
    private const PRODUCTS_COUNT = 5000;
    private const IMAGES_COUNT_PER_PRODUCT = 2;
    private const ORDERS_COUNT = 100000;
    private const ITEMS_PER_ORDER = 4;
    private const CHUNK_SIZE = 500;

    public function run(): void
    {
        
        $faker = FakerFactory::create();
        $faker->seed(20261108);
        mt_srand(20261108);

        $startedAt = microtime(true);

        $this->command->info('Starting Performance Dataset generation...');

        $this->cleanPerformanceDatabase();

        // Generate data in dependency order.
        $this->seedUsers($faker);
        $this->seedCategories($faker);

        $productPrices = $this->seedProducts($faker);

        $this->seedProductImages();

        $this->seedOrdersAndItems($productPrices);

        $elapsed = round(microtime(true) - $startedAt, 2);

        $this->command->newLine();
        $this->command->info('Performance Dataset generated successfully!');
        $this->command->table(
            ['Table', 'Expected Records'],
            [
                ['Users', self::USERS_COUNT],
                ['Categories', self::CATEGORIES_COUNT],
                ['Products', self::PRODUCTS_COUNT],
                [
                    'Product Images',
                    self::PRODUCTS_COUNT * self::IMAGES_COUNT_PER_PRODUCT
                ],
                ['Orders', self::ORDERS_COUNT],
                [
                    'Order Items',
                    self::ORDERS_COUNT * self::ITEMS_PER_ORDER
                ],
            ]
        );

        $this->command->info("Execution time: {$elapsed} seconds.");
    }

    /**
     * Remove previous performance data.
     */
    private function cleanPerformanceDatabase(): void
    {
        $this->command->info('Cleaning previous performance data...');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            $tables = [
                'order_items',
                'orders',
                'product_images',
                'products',
                'categories',
                'users',
            ];

            foreach ($tables as $table) {
                DB::table($table)->truncate();
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * Generate users.
     */
    private function seedUsers($faker): void
    {
        $this->command->info('Generating users...');

        $password = Hash::make('PerformanceTest@123');
        $now = now()->toDateTimeString();

        $rows = [];

        for ($i = 1; $i <= self::USERS_COUNT; $i++) {
            $rows[] = [
                'id' => $i,
                'name' => $faker->name(),
                'email' => "performance_user_{$i}@example.test",
                'email_verified_at' => $now,
                'password' => $password,
                'role' => $i <= self::RESPONSIBLE_COUNT
                    ? 'Responsible'
                    : 'Customer',
                'remember_token' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertInChunks('users', $rows);
    }

    /**
     * Generate categories.
     */
    private function seedCategories($faker): void
    {
        $this->command->info('Generating categories...');

        $rows = [];
        $now = now()->toDateTimeString();

        for ($i = 1; $i <= self::CATEGORIES_COUNT; $i++) {
            $rows[] = [
                'id' => $i,
                'name' => "Performance Category {$i}",
                'slug' => "performance-category-{$i}",
                'description' => $faker->sentence(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertInChunks('categories', $rows);
    }

    /**
     * Generate products and return their prices in cents.
     */
    private function seedProducts($faker): array
    {
        $this->command->info('Generating products...');

        $rows = [];
        $productPrices = [];
        $now = now()->toDateTimeString();

        for ($i = 1; $i <= self::PRODUCTS_COUNT; $i++) {
            $priceInCents = mt_rand(500, 100000);

            $productPrices[$i] = $priceInCents;

            $rows[] = [
                'id' => $i,
                'category_id' => mt_rand(1, self::CATEGORIES_COUNT),
                'name' => "Performance Product {$i}",
                'slug' => "performance-product-{$i}",
                'description' => $faker->paragraph(),
                'price' => number_format(
                    $priceInCents / 100,
                    2,
                    '.',
                    ''
                ),
                'is_active' => mt_rand(1, 100) <= 85,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertInChunks('products', $rows);

        return $productPrices;
    }

    /**
     * Generate product images.
     */
    private function seedProductImages(): void
    {
        $this->command->info('Generating product images...');

        $rows = [];
        $now = now()->toDateTimeString();

        $imageId = 1;

        for ($productId = 1; $productId <= self::PRODUCTS_COUNT; $productId++) {
            for (
                $sortOrder = 1;
                $sortOrder <= self::IMAGES_COUNT_PER_PRODUCT;
                $sortOrder++
            ) {
                $fileName = "performance-product-{$productId}-{$sortOrder}.jpg";

                $rows[] = [
                    'id' => $imageId++,
                    'product_id' => $productId,
                    'file_name' => $fileName,
                    'path' => "performance/products/{$fileName}",
                    'sort_order' => $sortOrder,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->insertInChunks('product_images', $rows);
    }

    /**
     * Generate orders and order items.
     *
     * Every order contains exactly four distinct products.
     */
    private function seedOrdersAndItems(array $productPrices): void
    {
        $this->command->info('Generating orders and order items...');

        $now = now()->toDateTimeString();
        $baseTimestamp = time();

        $ordersBatch = [];
        $itemsBatch = [];

        for ($orderId = 1; $orderId <= self::ORDERS_COUNT; $orderId++) {

            $status = $this->randomOrderStatus();

            $createdAt = date(
                'Y-m-d H:i:s',
                $baseTimestamp - mt_rand(0, 31536000)
            );

            $selectedProducts = [];

            while (count($selectedProducts) < self::ITEMS_PER_ORDER) {
                $productId = mt_rand(1, self::PRODUCTS_COUNT);

                $selectedProducts[$productId] = true;
            }

            $totalCents = 0;
            $orderItems = [];

            foreach (array_keys($selectedProducts) as $productId) {
                $quantity = mt_rand(1, 5);
                $unitPrice = $productPrices[$productId];

                $totalCents += $unitPrice * $quantity;

                $orderItems[] = [
                    'order_id' => $orderId,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => number_format(
                        $unitPrice / 100,
                        2,
                        '.',
                        ''
                    ),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }

            $ordersBatch[] = [
                'id' => $orderId,
                'user_id' => mt_rand(1, self::USERS_COUNT),
                'status' => $status,
                'total_amount' => number_format(
                    $totalCents / 100,
                    2,
                    '.',
                    ''
                ),
                'inventory_reservation_reference' => in_array(
                    $status,
                    ['Confirmed', 'Shipped', 'Delivered'],
                    true
                )
                    ? sprintf('PERF-RES-%010d', $orderId)
                    : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            foreach ($orderItems as $item) {
                $itemsBatch[] = $item;
            }

            // Insert each batch to control memory consumption.
            if (count($ordersBatch) >= self::CHUNK_SIZE) {
                DB::table('orders')->insert($ordersBatch);
                DB::table('order_items')->insert($itemsBatch);

                $ordersBatch = [];
                $itemsBatch = [];

                if ($orderId % 10000 === 0) {
                    $this->command->info(
                        "Generated {$orderId} / " . self::ORDERS_COUNT . ' orders...'
                    );
                }
            }
        }

        // Insert remaining records.
        if (!empty($ordersBatch)) {
            DB::table('orders')->insert($ordersBatch);
            DB::table('order_items')->insert($itemsBatch);
        }
    }

    /**
     * Generate realistic order status distribution.
     */
    private function randomOrderStatus(): string
    {
        $value = mt_rand(1, 100);

        return match (true) {
            $value <= 40 => 'Pending',
            $value <= 65 => 'Confirmed',
            $value <= 80 => 'Shipped',
            $value <= 95 => 'Delivered',
            default => 'Cancelled',
        };
    }

    /**
     * Insert records in manageable chunks.
     */
    private function insertInChunks(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
