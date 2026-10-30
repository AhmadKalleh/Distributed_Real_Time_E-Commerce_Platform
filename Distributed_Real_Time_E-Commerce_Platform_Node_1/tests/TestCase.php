<?php

namespace Tests;

use App\Models\User;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Users\Services\JwtService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * The JWT guard caches the resolved user for the lifetime of the app
     * instance; reset it so each test request authenticates from its own token.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app['auth']->forgetGuards();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    protected function tokenFor(User $user): string
    {
        return $this->app->make(JwtService::class)->issue($user)['token'];
    }

    /**
     * @return array<string, string>
     */
    protected function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.$this->tokenFor($user)];
    }

    protected function makeProduct(array $overrides = []): Product
    {
        $category = $overrides['category_id'] ?? Category::firstOrCreate(
            ['slug' => 'electronics'],
            ['name' => 'Electronics'],
        )->id;

        $name = $overrides['name'] ?? 'Product '.Str::random(5);

        return Product::create($overrides + [
            'category_id' => $category,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'price' => '10.00',
            'stock' => 10,
            'is_active' => true,
        ]);
    }
}
