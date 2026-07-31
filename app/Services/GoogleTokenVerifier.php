<?php

namespace App\Services;

use App\Contracts\GoogleTokenVerifierContract;
use Google\Client;

class GoogleTokenVerifier implements GoogleTokenVerifierContract
{
    public function verify(string $idToken): ?array
    {
        $client = new Client;
        $payload = $client->verifyIdToken($idToken);

        if (! is_array($payload)) {
            return null;
        }

        $allowedClientIds = config('services.google.allowed_client_ids', []);

        if (! in_array($payload['aud'] ?? null, $allowedClientIds, true)) {
            return null;
        }

        return $payload;
    }
}
