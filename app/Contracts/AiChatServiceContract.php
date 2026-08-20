<?php

namespace App\Contracts;

use App\Support\AiReply;

interface AiChatServiceContract
{
    public function answer(string $question, array $history = []): AiReply;
}
