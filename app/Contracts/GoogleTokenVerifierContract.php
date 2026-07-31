<?php

namespace App\Contracts;

interface GoogleTokenVerifierContract
{
    public function verify(string $idToken): ?array;
}
