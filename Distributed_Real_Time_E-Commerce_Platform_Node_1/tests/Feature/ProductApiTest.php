<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Products\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_responsible_can_add_a_product_with_images(): void
    {
        Storage::fake('public');
        $admin = User::factory()->responsible()->create();
        $category = Category::create(['name' => 'Books', 'slug' => 'books']);

        $response = $this->post('/api/v1/products', [
            'category_id' => $category->id,
            'name' => 'Clean Architecture',
            'description' => 'A book.',
            'price' => '29.99',
            'stock' => 7,
            'images' => [
                UploadedFile::fake()->image('cover.jpg', 600, 800),
                UploadedFile::fake()->image('back.png', 600, 800),
            ],
        ], $this->authHeaders($admin) + ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertHeader('Location')
            ->assertJsonPath('data.name', 'Clean Architecture')
            ->assertJsonPath('data.price', '29.99')
            ->assertJsonPath('data.category.slug', 'books')
            ->assertJsonCount(2, 'data.images');

        $this->assertCount(2, Storage::disk('public')->allFiles('products'));
        // Stored under random names, never the client-supplied one.
        $this->assertStringNotContainsString('cover', $response->json('data.images.0.url'));
    }

    public function test_non_image_upload_is_rejected(): void
    {
        Storage::fake('public');
        $admin = User::factory()->responsible()->create();
        $category = Category::create(['name' => 'Books', 'slug' => 'books']);

        $this->post('/api/v1/products', [
            'category_id' => $category->id,
            'name' => 'Bad upload',
            'price' => '5',
            'stock' => 1,
            'images' => [UploadedFile::fake()->create('shell.php', 10, 'application/x-php')],
        ], $this->authHeaders($admin) + ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['images.0']]]);
    }

    public function test_products_can_be_filtered_by_category_and_paginated(): void
    {
        $books = Category::create(['name' => 'Books', 'slug' => 'books']);
        $sports = Category::create(['name' => 'Sports', 'slug' => 'sports']);

        foreach (range(1, 5) as $i) {
            $this->makeProduct(['category_id' => $books->id, 'name' => "Book {$i}"]);
        }
        $this->makeProduct(['category_id' => $sports->id, 'name' => 'Ball']);
        $this->makeProduct(['category_id' => $books->id, 'name' => 'Hidden book', 'is_active' => false]);

        $this->getJson('/api/v1/products?category=books&per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('data.0.category.slug', 'books');

        $this->getJson("/api/v1/products?category={$sports->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ball');
    }

    public function test_invalid_list_parameters_return_422(): void
    {
        $this->getJson('/api/v1/products?per_page=1000')
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['per_page']]]);
    }

    public function test_show_product_and_404_for_missing_or_inactive(): void
    {
        $product = $this->makeProduct(['name' => 'Lamp']);
        $inactive = $this->makeProduct(['is_active' => false]);

        $this->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Lamp');

        $this->getJson("/api/v1/products/{$inactive->id}")->assertNotFound();
        $this->getJson('/api/v1/products/987654')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_adding_a_product_requires_authentication(): void
    {
        $this->postJson('/api/v1/products', [])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }
}
