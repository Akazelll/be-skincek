<?php

namespace App\Models;

use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected static function booted(): void
    {
        static::creating(function (self $token) {
            $token->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
