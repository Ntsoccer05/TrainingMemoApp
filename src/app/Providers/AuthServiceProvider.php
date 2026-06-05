<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;

use App\Mail\BareMail;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //
        ResetPassword::createUrlUsing(function (User $user, string $token) {
            // メールのパスワードリセットボタン押下時の遷移先
            return env('APP_URL').'/password/reset?token121212121='.$token.'1212121212&email='.$user->email; 
        });
    }
}
