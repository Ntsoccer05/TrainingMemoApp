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
        $this->app->booted(function () {
            error_log('[DEBUG-BOOT] ALL PROVIDERS BOOTED (app booted callback)');
        });
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Console\Events\CommandStarting::class, function ($event) {
            error_log('[DEBUG-BOOT] CommandStarting: ' . $event->command);
        });
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Console\Events\CommandFinished::class, function ($event) {
            error_log('[DEBUG-BOOT] CommandFinished: ' . $event->command . ' exitCode=' . $event->exitCode);
        });
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
