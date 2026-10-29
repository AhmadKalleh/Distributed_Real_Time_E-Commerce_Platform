<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categoryIds = DB::table('categories')->pluck('id', 'slug');

        $products = [
            ['category' => 'electronics', 'name' => 'Wireless Mouse', 'description' => 'Comfortable wireless mouse for daily work.', 'price' => '18.50'],
            ['category' => 'electronics', 'name' => 'USB-C Hub', 'description' => 'Multi-port USB-C adapter for laptops.', 'price' => '32.00'],
            ['category' => 'electronics', 'name' => 'Bluetooth Headphones', 'description' => 'Over-ear wireless headphones.', 'price' => '49.99'],
            ['category' => 'books', 'name' => 'Clean Code', 'description' => 'A software craftsmanship book.', 'price' => '24.00'],
            ['category' => 'books', 'name' => 'Database Design Basics', 'description' => 'An introductory database design book.', 'price' => '16.75'],
            ['category' => 'home-kitchen', 'name' => 'Insulated Bottle', 'description' => 'Reusable bottle for hot and cold drinks.', 'price' => '12.25'],
            ['category' => 'home-kitchen', 'name' => 'Desk Lamp', 'description' => 'Adjustable LED desk lamp.', 'price' => '27.90'],
            ['category' => 'sports', 'name' => 'Yoga Mat', 'description' => 'Non-slip exercise mat.', 'price' => '21.00'],
        ];

        foreach ($products as $product) {
            $categoryId = $categoryIds[$product['category']] ?? null;
            if (!$categoryId) {
                continue;
            }

            DB::table('products')->updateOrInsert(
                ['slug' => Str::slug($product['name'])],
                [
                    'category_id' => $categoryId,
                    'name' => $product['name'],
                    'description' => $product['description'],
                    'price' => $product['price'],
                    'stock' => 50,
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
