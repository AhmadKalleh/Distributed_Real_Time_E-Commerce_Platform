<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_places_an_order_with_server_side_prices_and_stock_is_reserved(): void
    {
        $customer = User::factory()->create();
        $mouse = $this->makeProduct(['price' => '18.50', 'stock' => 5]);
        $hub = $this->makeProduct(['price' => '32.00', 'stock' => 5]);

        $this->postJson('/api/v1/orders', [
            'items' => [
                ['product_id' => $mouse->id, 'quantity' => 2, 'unit_price' => '0.01'],
                ['product_id' => $hub->id, 'quantity' => 1],
                ['product_id' => $mouse->id, 'quantity' => 1],
            ],
        ], $this->authHeaders($customer))
            ->assertCreated()
            ->assertHeader('Location')
            ->assertJsonPath('data.status', 'Pending')
            ->assertJsonPath('data.total_amount', '87.50')
            ->assertJsonCount(2, 'data.items');

        $this->assertSame(2, $mouse->fresh()->stock);
        $this->assertSame(4, $hub->fresh()->stock);
    }

    public function test_order_is_rejected_when_stock_is_insufficient(): void
    {
        $customer = User::factory()->create();
        $product = $this->makeProduct(['stock' => 1]);

        $this->postJson('/api/v1/orders', ['items' => [['product_id' => $product->id, 'quantity' => 3]]], $this->authHeaders($customer))
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'INSUFFICIENT_STOCK')
            ->assertJsonPath('error.details.items.0.available', 1);

        $this->assertSame(1, $product->fresh()->stock);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_status_follows_the_allowed_lifecycle(): void
    {
        $customer = User::factory()->create();
        $admin = User::factory()->responsible()->create();
        $product = $this->makeProduct();

        $orderId = $this->postJson('/api/v1/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]], $this->authHeaders($customer))
            ->json('data.id');

        $adminHeaders = $this->authHeaders($admin);

        $this->patchJson("/api/v1/orders/{$orderId}/status", ['status' => 'Delivered'], $adminHeaders)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS_TRANSITION')
            ->assertJsonPath('error.details.allowed', ['Confirmed', 'Cancelled']);

        foreach (['Confirmed', 'Shipped', 'Delivered'] as $status) {
            $this->patchJson("/api/v1/orders/{$orderId}/status", ['status' => $status], $adminHeaders)
                ->assertOk()
                ->assertJsonPath('data.status', $status);
        }

        $this->patchJson("/api/v1/orders/{$orderId}/status", ['status' => 'Bogus'], $adminHeaders)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_cancelling_returns_stock(): void
    {
        $customer = User::factory()->create();
        $admin = User::factory()->responsible()->create();
        $product = $this->makeProduct(['stock' => 10]);

        $orderId = $this->postJson('/api/v1/orders', ['items' => [['product_id' => $product->id, 'quantity' => 4]]], $this->authHeaders($customer))
            ->json('data.id');
        $this->assertSame(6, $product->fresh()->stock);

        $this->patchJson("/api/v1/orders/{$orderId}/status", ['status' => 'Cancelled'], $this->authHeaders($admin))
            ->assertOk();

        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_status_change_notifies_the_order_owner(): void
    {
        $customer = User::factory()->create();
        $admin = User::factory()->responsible()->create();
        $product = $this->makeProduct();

        $orderId = $this->postJson('/api/v1/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]], $this->authHeaders($customer))
            ->json('data.id');

        // The admin is told about the new order.
        $this->getJson('/api/v1/notifications', $this->authHeaders($admin))
            ->assertOk()
            ->assertJsonPath('data.0.type', 'order.placed');

        $this->patchJson("/api/v1/orders/{$orderId}/status", ['status' => 'Confirmed'], $this->authHeaders($admin))->assertOk();

        $notificationId = $this->getJson('/api/v1/notifications?unread=1', $this->authHeaders($customer))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'order.status_changed')
            ->assertJsonPath('data.0.payload.to', 'Confirmed')
            ->json('data.0.id');

        $this->patchJson("/api/v1/notifications/{$notificationId}/read", [], $this->authHeaders($customer))
            ->assertOk()
            ->assertJsonPath('data.id', $notificationId);

        $this->getJson('/api/v1/notifications?unread=1', $this->authHeaders($customer))
            ->assertJsonCount(1, 'data');
    }
}
