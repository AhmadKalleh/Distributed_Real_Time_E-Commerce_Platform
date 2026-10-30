<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_always_creates_a_customer_even_if_role_is_sent(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Mallory',
            'email' => 'mallory@example.test',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'role' => 'Responsible',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.role', 'Customer')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['access_token', 'expires_in', 'user' => ['id', 'email']]]);

        $this->assertDatabaseHas('users', ['email' => 'mallory@example.test', 'role' => 'Customer']);
    }

    public function test_register_validates_input_in_the_unified_error_format(): void
    {
        $this->postJson('/api/v1/auth/register', ['email' => 'not-an-email', 'password' => 'short'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['status', 'code', 'message', 'details' => ['name', 'email', 'password']]]);
    }

    public function test_login_returns_a_jwt_that_works_on_protected_routes(): void
    {
        $user = User::factory()->create(['email' => 'c@example.test', 'password' => 'Secret123']);

        $token = $this->postJson('/api/v1/auth/login', ['email' => 'c@example.test', 'password' => 'Secret123'])
            ->assertOk()
            ->json('data.access_token');

        $this->assertCount(3, explode('.', $token));

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonMissingPath('data.password');
    }

    public function test_wrong_password_is_rejected_without_revealing_which_field_was_wrong(): void
    {
        User::factory()->create(['email' => 'c@example.test', 'password' => 'Secret123']);

        $wrongPassword = $this->postJson('/api/v1/auth/login', ['email' => 'c@example.test', 'password' => 'nope'])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');

        $unknownEmail = $this->postJson('/api/v1/auth/login', ['email' => 'ghost@example.test', 'password' => 'nope'])
            ->assertUnauthorized();

        $this->assertSame($wrongPassword->json('error.message'), $unknownEmail->json('error.message'));
    }

    public function test_login_is_locked_after_too_many_failed_attempts(): void
    {
        User::factory()->create(['email' => 'c@example.test', 'password' => 'Secret123']);
        $max = config('security.login.max_attempts');

        for ($i = 1; $i <= $max; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'c@example.test', 'password' => 'wrong'])
                ->assertUnauthorized()
                ->assertJsonPath('error.details.attempts_left', $max - $i);
        }

        // Locked: even the correct password is refused until the lockout expires.
        $this->postJson('/api/v1/auth/login', ['email' => 'c@example.test', 'password' => 'Secret123'])
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('error.code', 'TOO_MANY_LOGIN_ATTEMPTS');

        $this->travel(config('security.login.lockout_seconds') + 1)->seconds();

        $this->postJson('/api/v1/auth/login', ['email' => 'c@example.test', 'password' => 'Secret123'])
            ->assertOk();
    }

    public function test_auth_endpoints_are_rate_limited_per_ip(): void
    {
        $limit = config('security.login.per_ip_per_minute');

        for ($i = 0; $i < $limit; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => "u{$i}@example.test", 'password' => 'x']);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'another@example.test', 'password' => 'x'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'TOO_MANY_REQUESTS');
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = User::factory()->create();
        $headers = $this->authHeaders($user);

        $this->getJson('/api/v1/auth/me', $headers)->assertOk();
        $this->postJson('/api/v1/auth/logout', [], $headers)->assertNoContent();
        $this->getJson('/api/v1/auth/me', $headers)->assertUnauthorized();
    }

    public function test_expired_token_is_rejected(): void
    {
        $headers = $this->authHeaders(User::factory()->create());

        $this->travel(config('jwt.ttl') + 1)->minutes();

        $this->getJson('/api/v1/auth/me', $headers)->assertUnauthorized();
    }

    public function test_demoted_user_loses_admin_rights_immediately(): void
    {
        $admin = User::factory()->responsible()->create();
        $headers = $this->authHeaders($admin);

        $this->getJson('/api/v1/users', $headers)->assertOk();

        $admin->forceFill(['role' => 'Customer'])->save();

        $this->getJson('/api/v1/users', $headers)->assertForbidden();
    }
}
