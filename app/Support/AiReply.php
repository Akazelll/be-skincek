<?php

namespace App\Support;

class AiReply
{
    public function __construct(
        public readonly string $answer,
        public readonly string $source = 'gemini',
    ) {}
}
