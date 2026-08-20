<?php

namespace App\Services;

use App\Contracts\AiChatServiceContract;
use App\Support\AiReply;
use Throwable;

class AiChatService implements AiChatServiceContract
{
    public function __construct(
        private readonly GeminiAiProvider $provider,
    ) {}

    public function answer(string $question, array $history = []): AiReply
    {
        if ($this->triggersEscalation($question)) {
            return new AiReply((string) config('ai.escalation_reply'), 'escalation');
        }

        try {
            return $this->provider->answer($question, $history);
        } catch (Throwable $e) {
            report($e);

            return new AiReply((string) config('ai.error_reply'), 'error');
        }
    }

    private function triggersEscalation(string $question): bool
    {
        $lower = mb_strtolower($question);

        foreach (config('ai.unsafe_keywords', []) as $keyword) {
            if ($keyword !== '' && mb_strpos($lower, mb_strtolower($keyword)) !== false) {
                return true;
            }
        }

        return false;
    }
}
