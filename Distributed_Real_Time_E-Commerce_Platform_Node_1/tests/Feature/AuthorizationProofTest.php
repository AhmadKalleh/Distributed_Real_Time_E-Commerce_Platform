<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Notifications\Models\UserNotification;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Proof for requirement 1: any attempt by a user to reach another user's
 * order, or to do something outside their role, is rejected.
 */
class AuthorizationProofTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;
    private User $bob;
    private User $admin;
    private Order $aliceOrder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = User::factory()->create(['email' => 'alice@example.test']);
        $this->bob = User::factory()->create(['email' => 'bob@example.test']);
        $this->admin = User::factory()->responsible()->create();

        $product = $this->makeProduct(['price' => '20.00', 'stock' => 10]);

        $orderId = $this->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ], $this->authHeaders($this->alice))->assertCreated()->json('data.id');

        $this->aliceOrder = Order::findOrFail($orderId);
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $this->getJson("/api/v1/orders/{$this->aliceOrder->id}", $this->authHeaders($this->bob))
            ->assertForbidden()
            ->assertExactJson(['error' => [
                'status' => 403,
                'code' => 'FORBIDDEN',
                'message' => 'You are not allowed to perform this action.',
            ]]);
    }

    public function test_owner_can_view_their_own_order(): void
    {
        $this->getJson("/api/v1/orders/{$this->aliceOrder->id}", $this->authHeaders($this->alice))
            ->assertOk()
            ->assertJsonPath('data.id', $this->aliceOrder->id)
            ->assertJsonPath('data.user_id', $this->alice->id);
    }

    public function test_order_list_only_contains_the_customers_own_orders(): void
    {
        $this->getJson('/api/v1/orders', $this->authHeaders($this->bob))
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);

        $this->getJson('/api/v1/orders', $this->authHeaders($this->alice))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $this->alice->id);
    }

    public function test_responsible_can_view_any_order(): void
    {
        $this->getJson("/api/v1/orders/{$this->aliceOrder->id}", $this->authHeaders($this->admin))
            ->assertOk();

        $this->getJson('/api/v1/orders', $this->authHeaders($this->admin))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_customer_cannot_change_order_status_even_on_their_own_order(): void
    {
        $this->patchJson("/api/v1/orders/{$this->aliceOrder->id}/status", ['status' => 'Delivered'], $this->authHeaders($this->alice))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->patchJson("/api/v1/orders/{$this->aliceOrder->id}/status", ['status' => 'Cancelled'], $this->authHeaders($this->bob))
            ->assertForbidden();

        $this->assertSame('Pending', $this->aliceOrder->fresh()->status->value);
    }

    public function test_only_responsible_can_change_order_status(): void
    {
        $this->patchJson("/api/v1/orders/{$this->aliceOrder->id}/status", ['status' => 'Confirmed'], $this->authHeaders($this->admin))
            ->assertOk()
            ->assertJsonPath('data.status', 'Confirmed');
    }

    public function test_customer_cannot_add_products(): void
    {
        Storage::fake('public');

        $this->post('/api/v1/products', [
            'category_id' => 1,
            'name' => 'Hacked product',
            'price' => 1,
            'stock' => 1,
            'images' => [UploadedFile::fake()->image('x.jpg')],
        ], $this->authHeaders($this->alice) + ['Accept' => 'application/json'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->assertDatabaseMissing('products', ['name' => 'Hacked product']);
    }

    public function test_customer_cannot_list_users(): void
    {
        $this->getJson('/api/v1/users', $this->authHeaders($this->alice))
            ->assertForbidden();

        $this->getJson('/api/v1/users', $this->authHeaders($this->admin))
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }

    public function test_responsible_cannot_place_orders(): void
    {
        $this->postJson('/api/v1/orders', ['items' => [['product_id' => 1, 'quantity' => 1]]], $this->authHeaders($this->admin))
            ->assertForbidden();
    }

    public function test_user_cannot_read_or_mark_another_users_notification(): void
    {
        $aliceNotification = UserNotification::where('user_id', $this->alice->id)->firstOrFail();

        $this->patchJson("/api/v1/notifications/{$aliceNotification->id}/read", [], $this->authHeaders($this->bob))
            ->assertForbidden();

        // Not even the Responsible may touch a customer's notifications.
        $this->patchJson("/api/v1/notifications/{$aliceNotification->id}/read", [], $this->authHeaders($this->admin))
            ->assertForbidden();

        $this->assertNull($aliceNotification->fresh()->read_at);

        $this->getJson('/api/v1/notifications', $this->authHeaders($this->bob))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_requests_without_a_valid_token_are_rejected(): void
    {
        $id = $this->aliceOrder->id;

        $this->getJson("/api/v1/orders/{$id}")
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->getJson("/api/v1/orders/{$id}", ['Authorization' => 'Bearer not-a-real-token'])
            ->assertUnauthorized();

        $this->patchJson("/api/v1/orders/{$id}/status", ['status' => 'Confirmed'])
            ->assertUnauthorized();
    }

    public function test_a_forged_token_claiming_the_admin_role_is_rejected(): void
    {
        [$header, $payload, $signature] = explode('.', $this->tokenFor($this->alice));

        $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        $claims['role'] = 'Responsible';
        $claims['sub'] = (string) $this->admin->id;
        $forgedPayload = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');

        $this->patchJson("/api/v1/orders/{$this->aliceOrder->id}/status", ['status' => 'Confirmed'], [
            'Authorization' => "Bearer {$header}.{$forgedPayload}.{$signature}",
        ])->assertUnauthorized();
    }

    public function test_a_token_signed_with_alg_none_is_rejected(): void
    {
        $encode = fn (array $part) => rtrim(strtr(base64_encode(json_encode($part)), '+/', '-_'), '=');
        $token = $encode(['alg' => 'none', 'typ' => 'JWT']).'.'.$encode([
            'iss' => config('jwt.issuer'), 'sub' => (string) $this->admin->id, 'role' => 'Responsible',
            'iat' => time(), 'exp' => time() + 3600, 'jti' => 'x',
        ]).'.';

        $this->getJson('/api/v1/users', ['Authorization' => "Bearer {$token}"])
            ->assertUnauthorized();
    }

    public function test_unknown_order_returns_404_in_the_unified_format(): void
    {
        $this->getJson('/api/v1/orders/999999', $this->authHeaders($this->admin))
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND')
            ->assertJsonPath('error.status', 404);
    }
}
