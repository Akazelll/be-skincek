<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoginActivityResource extends JsonResource
{
    public function __construct($resource, private ?array $location = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'device' => $this->deviceName($this->user_agent),
            'ip_address' => $this->ip_address,
            'location' => $this->location,
            'is_current' => $request->user()->currentAccessToken()?->getKey() === $this->getKey(),
            'last_used_at' => $this->last_used_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function deviceName(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'Perangkat tidak dikenal';
        }

        $platform = match (true) {
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Perangkat tidak dikenal',
        };
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => null,
        };

        return $browser ? "{$browser} di {$platform}" : $platform;
    }
}
