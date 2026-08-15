<?php

namespace App\Services;

use App\Contracts\IpLocationResolverContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class IpApiLocationResolver implements IpLocationResolverContract
{
    private const CACHE_KEY = 'ip-location:%s';

    private const FRESH_KEY = 'ip-location-fresh:%s';

    private const RATE_LIMITER_KEY = 'ip-location';

    public function resolve(?string $ip): ?array
    {
        if (! $this->isResolvable($ip)) {
            return null;
        }

        $cacheKey = sprintf(self::CACHE_KEY, $ip);
        $freshKey = sprintf(self::FRESH_KEY, $ip);

        if (Cache::has($freshKey) && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        if (RateLimiter::tooManyAttempts(self::RATE_LIMITER_KEY, (int) config('services.ip_location.limit_per_minute', 45))) {
            return Cache::get($cacheKey);
        }

        $location = $this->fetch($ip);

        RateLimiter::hit(self::RATE_LIMITER_KEY, 60);
        Cache::put($freshKey, true, now()->addSeconds((int) config('services.ip_location.cache_ttl', 86400)));

        if ($location !== null) {
            Cache::put($cacheKey, $location);
        }

        return $location;
    }

    private function fetch(string $ip): ?array
    {
        try {
            $data = Http::timeout(3)->get(
                'http://ip-api.com/json/'.$ip,
                ['fields' => 'status,country,city,regionName', 'lang' => 'id']
            )->throw()->json();
        } catch (Throwable) {
            return null;
        }

        if (($data['status'] ?? null) !== 'success' || empty($data['city'])) {
            return null;
        }

        return [
            'city' => $data['city'],
            'region' => $data['regionName'] ?? null,
            'country' => $data['country'] ?? null,
        ];
    }

    private function isResolvable(?string $ip): bool
    {
        if (! $ip) {
            return false;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
