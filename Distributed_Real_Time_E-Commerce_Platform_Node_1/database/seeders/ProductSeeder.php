<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $categoryIds = DB::table('categories')->pluck('id', 'slug');

        $products = [
            [
                'category' => 'electronics',
                'name' => 'Wireless Mouse',
                'description' => 'Comfortable wireless mouse for daily work.',
                'price' => '18.50',
                'image' => 'Wireless_Mouse.jpg',
            ],
            [
                'category' => 'electronics',
                'name' => 'USB-C Hub',
                'description' => 'Multi-port USB-C adapter for laptops.',
                'price' => '32.00',
                'image' => 'USB_C_Hub.jpg',
            ],
            [
                'category' => 'electronics',
                'name' => 'Bluetooth Headphones',
                'description' => 'Over-ear wireless headphones.',
                'price' => '49.99',
                'image' => 'Bluetooth_Headphones.jpg',
            ],
            [
                'category' => 'books',
                'name' => 'Clean Code',
                'description' => 'A software craftsmanship book.',
                'price' => '24.00',
                'image' => 'Clean_Code.jpg',
            ],
            [
                'category' => 'books',
                'name' => 'Database Design Basics',
                'description' => 'An introductory database design book.',
                'price' => '16.75',
                'image' => 'Database_Design_Basics.jpg',
            ],
            [
                'category' => 'home-kitchen',
                'name' => 'Insulated Bottle',
                'description' => 'Reusable bottle for hot and cold drinks.',
                'price' => '12.25',
                'image' => 'Insulated_Bottle.jpg',
            ],
            [
                'category' => 'home-kitchen',
                'name' => 'Desk Lamp',
                'description' => 'Adjustable LED desk lamp.',
                'price' => '27.90',
                'image' => 'Desk_Lamp.jpg',
            ],
            [
                'category' => 'sports',
                'name' => 'Yoga Mat',
                'description' => 'Non-slip exercise mat.',
                'price' => '21.00',
                'image' => 'Yoga_Mat.jpg',
            ],
        ];

        foreach ($products as $product) {
            $categoryId = $categoryIds[$product['category']] ?? null;

            if (! $categoryId) {
                continue;
            }

            $slug = Str::slug($product['name']);

            DB::table('products')->updateOrInsert(
                ['slug' => $slug],
                [
                    'category_id' => $categoryId,
                    'name' => $product['name'],
                    'description' => $product['description'],
                    'price' => $product['price'],
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            $productId = DB::table('products')
                ->where('slug', $slug)
                ->value('id');

            $fileName = $product['image'];

            DB::table('product_images')->updateOrInsert(
                ['file_name' => $fileName],
                [
                    'product_id' => $productId,
                    'path' => "products/{$fileName}",
                    'sort_order' => 1,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }
}
