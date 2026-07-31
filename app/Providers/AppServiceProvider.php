<?php

namespace App\Providers;

use App\Contracts\GoogleTokenVerifierContract;
use App\Contracts\SkinPredictionServiceContract;
use App\Models\PersonalAccessToken;
use App\Services\GoogleTokenVerifier;
use App\Services\HttpSkinPredictionService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SkinPredictionServiceContract::class, HttpSkinPredictionService::class);
        $this->app->bind(GoogleTokenVerifierContract::class, GoogleTokenVerifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        RateLimiter::for('google-auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}
