<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_responses_carry_security_headers(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Strict-Transport-Security')
            ->assertHeader('Permissions-Policy')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'")
            ->assertHeaderMissing('X-Powered-By');
    }

    public function test_error_responses_also_carry_security_headers(): void
    {
        $this->getJson('/api/v1/orders')
            ->assertUnauthorized()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_authenticated_responses_are_not_cacheable(): void
    {
        $this->getJson('/api/v1/auth/me', $this->authHeaders(User::factory()->create()))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_api_docs_are_served(): void
    {
        $this->get('/docs')->assertOk()->assertSee('swagger-ui');
        $this->get('/docs/openapi.yaml')->assertOk()->assertSee('openapi: 3.0.3', false);
    }

    public function test_unknown_api_route_and_wrong_method_use_the_unified_error_format(): void
    {
        $this->getJson('/api/v1/nope')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');

        $this->deleteJson('/api/v1/products')
            ->assertStatus(405)
            ->assertJsonPath('error.code', 'METHOD_NOT_ALLOWED');
    }
}
