<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

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
     *
     * @return void
     */
    public function boot()
    {
        error_log('[DEBUG-BOOT] RouteServiceProvider boot start');
        $this->configureRateLimiting();
        error_log('[DEBUG-BOOT] RouteServiceProvider after configureRateLimiting');

        $this->routes(function () {
            error_log('[DEBUG-BOOT] RouteServiceProvider routes closure start');
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));
            error_log('[DEBUG-BOOT] RouteServiceProvider after api.php');

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
            error_log('[DEBUG-BOOT] RouteServiceProvider after web.php');
        });
        error_log('[DEBUG-BOOT] RouteServiceProvider boot end');
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(1000)->by($request->user()?->id ?: $request->ip());
        });
    }
}
