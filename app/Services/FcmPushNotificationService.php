<?php

namespace App\Services;

use App\Contracts\PushNotificationServiceContract;
use App\Models\User;
use Google\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FcmPushNotificationService implements PushNotificationServiceContract
{
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $user->deviceTokens()->pluck('fcm_token')->each(function (string $token) use ($title, $body, $data) {
            $this->send($token, $title, $body, $data);
        });
    }

    public function send(string $token, string $title, string $body, array $data = []): void
    {
        try {
            $projectId = config('services.fcm.project_id');
            $url = sprintf((string) config('services.fcm.api_url'), $projectId);

            Http::timeout(10)
                ->withToken($this->accessToken())
                ->acceptJson()
                ->post($url, [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => array_map('strval', $data),
                    ],
                ])
                ->throw();
        } catch (Throwable $e) {
            Log::warning('FCM push notification failed', ['error' => $e->getMessage()]);
        }
    }

    protected function accessToken(): string
    {
        return Cache::remember('fcm.access-token', now()->addMinutes(55), function () {
            $client = new Client;
            $client->setAuthConfig(config('services.fcm.credentials_json'));
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

            $token = $client->fetchAccessTokenWithAssertion();

            return $token['access_token'];
        });
    }

    protected function isEnabled(): bool
    {
        return (bool) config('services.fcm.enabled')
            && (bool) config('services.fcm.project_id')
            && (bool) config('services.fcm.credentials_json');
    }
}
