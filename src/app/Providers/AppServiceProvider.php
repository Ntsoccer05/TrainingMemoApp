<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
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
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        if ($this->app->environment('production')) {
            // CloudFrontは/admin*等のキャッシュビヘイビアでHostヘッダーを転送していないため、
            // Lambda(API Gateway経由)が受け取るHostはexecute-apiドメインになる。
            // これをそのままURL生成に使うとFilamentのログインリダイレクト等が
            // execute-apiドメインへ飛んでしまうため、常にAPP_URL(training-memo.com)を使う。
            URL::forceRootUrl(config('app.url'));
            URL::forceScheme('https');
        }
    }
}
