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
        $this->app->bind(GoogleTokenVerifierContract::class, GoogleTokenVerifier::class);

        $mlDriver = (string) config('services.ml.driver', 'http');

        $this->app->bind(SkinPredictionServiceContract::class, match ($mlDriver) {
            'http' => HttpSkinPredictionService::class,
            default => HttpSkinPredictionService::class,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        RateLimiter::for('api', fn (Request $request) => app()->environment('testing')
            ? Limit::none()
            : Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('email', '')).'|'.$request->ip()));

        RateLimiter::for('forgot-password', fn (Request $request) => Limit::perMinutes(15, 3)
            ->by(mb_strtolower((string) $request->input('email', '')).'|'.$request->ip()));

        RateLimiter::for('reset-password', fn (Request $request) => Limit::perMinute(10)
            ->by(mb_strtolower((string) $request->input('email', '')).'|'.$request->ip()));

        RateLimiter::for('scans', fn (Request $request) => Limit::perMinute(30)
            ->by($request->user()?->uuid ?: $request->ip()));

        RateLimiter::for('google-auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}
