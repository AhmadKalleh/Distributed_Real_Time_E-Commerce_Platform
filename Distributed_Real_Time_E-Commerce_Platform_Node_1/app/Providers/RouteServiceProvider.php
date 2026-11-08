<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Http\Middleware\EnsurePerformanceTesting;
use LogicException;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            if (config('app.performance_testing')) {
                if ($this->app->environment('production')) {
                    throw new LogicException('Performance test routes cannot be enabled in production.');
                }

                Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/performance.php'));
            }

                Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });

        $modulesPath = app_path('Modules');

        if (File::isDirectory($modulesPath)) {
            $modules = File::directories($modulesPath);

            foreach ($modules as $module) {
                $routesFile = $module . '/Presentation/Http/routes.php';

                if (File::exists($routesFile)) {
                    Route::middleware('api')
                        ->prefix('api')
                        ->group($routesFile);
                }
            }
        }
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower(
                trim((string) $request->input('email'))
            );

            return Limit::perMinute(5)->by(
                $email . '|' . $request->ip()
            );
        });
    }
}
