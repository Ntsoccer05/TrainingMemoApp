<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Database\Eloquent\Model;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        error_log('[DEBUG-BOOT] AppServiceProvider register');
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        error_log('[DEBUG-BOOT] AppServiceProvider boot start');
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        error_log('[DEBUG-BOOT] AppServiceProvider boot end');
    }
}
