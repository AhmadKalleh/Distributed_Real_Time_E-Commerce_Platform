<?php

namespace App\Modules\Users;

use App\Models\User;
use App\Modules\Users\Services\JwtService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class UsersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(JwtService::class);
    }

    public function boot(): void
    {
        // The "api" guard (config/auth.php) uses this driver: it reads the Bearer
        // token, verifies it and loads the user fresh from the database, so a
        // changed role or deleted account takes effect immediately.
        Auth::viaRequest('jwt', function (Request $request) {
            $token = $request->bearerToken();
            if (! $token) {
                return null;
            }

            $claims = $this->app->make(JwtService::class)->decode($token);
            if (! $claims) {
                return null;
            }

            $request->attributes->set('jwt_claims', $claims);

            return User::find($claims->sub);
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(config('security.login.per_ip_per_minute'))->by($request->ip());
        });

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/routes.php');
    }
}
