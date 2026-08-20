<?php

namespace App\Services;

use App\Contracts\AiChatServiceContract;
use App\Support\AiReply;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiAiProvider implements AiChatServiceContract
{
    public function answer(string $question, array $history = []): AiReply
    {
        $apiKey = (string) config('ai.gemini.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('GEMINI_API_KEY belum diatur.');
        }

        $model = (string) config('ai.gemini.model', 'gemini-2.5-flash');
        $url = str_replace('{model}', $model, (string) config('ai.gemini.url'));

        $contents = [];

        foreach ($history as $entry) {
            $contents[] = [
                'role' => $entry['role'] === 'model' ? 'model' : 'user',
                'parts' => [['text' => (string) ($entry['content'] ?? '')]],
            ];
        }

        $contents[] = ['role' => 'user', 'parts' => [['text' => $question]]];

        $response = Http::timeout((int) config('ai.gemini.timeout', 30))
            ->withHeaders(['x-goog-api-key' => $apiKey])
            ->post($url, [
                'system_instruction' => ['parts' => [['text' => (string) config('ai.system_prompt')]]],
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => (float) config('ai.gemini.temperature', 0.4),
                    'maxOutputTokens' => (int) config('ai.gemini.max_output_tokens', 600),
                ],
            ]);

        $response->throw();

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('Balasan Gemini kosong.');
        }

        return new AiReply(trim($text), 'gemini');
    }
}
