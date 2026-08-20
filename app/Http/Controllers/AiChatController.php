<?php

namespace App\Http\Controllers;

use App\Http\Resources\ConversationResource;
use App\Models\AiChatConsent;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;

class AiChatController extends Controller
{
    public function consent(Request $request)
    {
        $latest = $request->user()->aiChatConsents()->latest('accepted_at')->first();

        return $this->successResponse([
            'accepted' => $request->user()->hasConsentedToAiChat(),
            'version' => config('ai.consent_version'),
            'text' => config('ai.consent_text'),
            'accepted_at' => $latest?->accepted_at?->toISOString(),
        ]);
    }

    public function updateConsent(Request $request)
    {
        $validated = $request->validate([
            'accepted' => ['required', 'boolean'],
        ]);

        if (! $validated['accepted']) {
            AiChatConsent::where('user_id', $request->user()->id)->delete();

            return $this->successResponse(['accepted' => false], ['message' => 'Persetujuan penggunaan AI telah dicabut']);
        }

        $request->user()->aiChatConsents()->updateOrCreate(
            ['consent_version' => config('ai.consent_version')],
            ['accepted_at' => now(), 'ip_address' => $request->ip()]
        );

        return $this->successResponse(['accepted' => true], ['message' => 'Persetujuan penggunaan AI disimpan']);
    }

    public function startConversation(Request $request)
    {
        $user = $request->user();

        abort_unless($user->hasConsentedToAiChat(), 403, 'Setujui penggunaan AI terlebih dahulu sebelum mengobrol dengan Aura Skin.');

        $bot = User::where('ai_bot', true)->firstOrFail();

        $conversation = Conversation::firstOrCreate([
            'user_id' => $user->id,
            'doctor_id' => $bot->id,
        ]);

        return (new ConversationResource(
            $conversation->fresh(['user', 'doctor', 'messages'])
        ))->response()->setStatusCode(201);
    }

    public function destroyConversation(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        abort_unless(
            $conversation->user_id === $user->id || $conversation->doctor_id === $user->id,
            404
        );

        $bot = User::where('ai_bot', true)->first();

        abort_unless($bot && $conversation->doctor_id === $bot->id, 422, 'Hanya riwayat chat Aura Skin yang dapat dihapus.');

        $conversation->delete();

        return $this->successResponse(null, ['message' => 'Riwayat chat Aura Skin telah dihapus']);
    }
}
