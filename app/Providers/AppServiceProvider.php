<?php

namespace App\Providers;

use App\Contracts\AiChatServiceContract;
use App\Contracts\GoogleTokenVerifierContract;
use App\Contracts\IpLocationResolverContract;
use App\Contracts\PushNotificationServiceContract;
use App\Contracts\SkinPredictionServiceContract;
use App\Models\PersonalAccessToken;
use App\Services\AiChatService;
use App\Services\FcmPushNotificationService;
use App\Services\GoogleTokenVerifier;
use App\Services\HttpSkinPredictionService;
use App\Services\IpApiLocationResolver;
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

        $this->app->bind(IpLocationResolverContract::class, match (config('services.ip_location.driver', 'ip-api')) {
            'ip-api' => IpApiLocationResolver::class,
            default => IpApiLocationResolver::class,
        });

        $mlDriver = (string) config('services.ml.driver', 'http');

        $this->app->bind(SkinPredictionServiceContract::class, match ($mlDriver) {
            'http' => HttpSkinPredictionService::class,
            default => HttpSkinPredictionService::class,
        });

        $this->app->bind(PushNotificationServiceContract::class, FcmPushNotificationService::class);

        $this->app->bind(AiChatServiceContract::class, AiChatService::class);
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
