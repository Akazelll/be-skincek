<?php

namespace App\Http\Controllers;

use App\Enums\VerificationStatus;
use App\Events\MessageSent;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Jobs\GenerateAiReply;
use App\Mail\MessageNotificationMail;
use App\Models\Conversation;
use App\Models\DoctorVerification;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Support\ImageExifStripper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class ConversationController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'doctor_id' => ['required', 'uuid', 'exists:users,uuid'],
        ]);

        $doctor = User::where('uuid', $validated['doctor_id'])->first();

        if (! $doctor->hasRole('doctor')) {
            return $this->errorResponse('Dokter tidak ditemukan', 422);
        }

        $verified = DoctorVerification::where('doctor_id', $doctor->id)
            ->where('verification_status', VerificationStatus::APPROVED)
            ->exists();

        if (! $verified) {
            return $this->errorResponse('Dokter belum terverifikasi', 422);
        }

        $conversation = Conversation::firstOrCreate([
            'user_id' => $request->user()->id,
            'doctor_id' => $doctor->id,
        ]);

        return (new ConversationResource(
            $conversation->fresh(['user', 'doctor', 'messages'])
        ))->response()->setStatusCode(201);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->with(['user.roles', 'user.media', 'doctor.roles', 'doctor.media', 'messages' => fn ($query) => $query->with('sender.roles', 'sender.media')->latest()->limit(1)])
            ->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('doctor_id', $user->id))
            ->latest()
            ->paginate($this->perPage($request));

        return ConversationResource::collection($conversations);
    }

    public function messages(Request $request, Conversation $conversation)
    {
        $this->assertParticipant($request->user(), $conversation);

        $messages = $conversation->messages()
            ->with('sender.roles', 'sender.media')
            ->latest()
            ->paginate($this->perPage($request));

        return MessageResource::collection($messages);
    }

    public function storeMessage(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        $this->assertParticipant($user, $conversation);

        $validated = $request->validate([
            'content' => ['required_without:media', 'nullable', 'string', 'max:5000'],
            'media' => ['nullable', 'file', 'mimes:jpeg,jpg,png,gif,webp,mp4,mov,avi', 'max:10240'],
        ]);

        $isUserSender = $conversation->user_id === $user->id;
        $recipientIsAiBot = $isUserSender && (bool) $conversation->doctor?->ai_bot;

        if ($isUserSender) {
            abort_unless($user->hasVerifiedEmail(), 403, 'Verifikasi email terlebih dahulu sebelum menggunakan fitur ini.');

            if ($recipientIsAiBot) {
                abort_unless($user->hasConsentedToAiChat(), 403, 'Setujui penggunaan AI terlebih dahulu sebelum mengobrol dengan Aura Skin.');
                $this->assertWithinAiChatQuota($user);
            }
        }

        $hasMedia = $request->hasFile('media');
        $mediaFile = $hasMedia ? $request->file('media') : null;
        $type = 'text';

        if ($hasMedia) {
            $mime = $mediaFile->getMimeType();
            $type = str_starts_with($mime, 'video/') ? 'video' : 'image';
        }

        $this->assertCleanContent($validated['content'] ?? null);

        $message = DB::transaction(function () use ($conversation, $user, $validated, $isUserSender, $recipientIsAiBot, $hasMedia, $mediaFile, $type) {
            $locked = Conversation::whereKey($conversation->getKey())->lockForUpdate()->first();

            if ($isUserSender && ! $recipientIsAiBot && $user->hasReachedFreeChatQuota()) {
                abort(402, 'Kamu sudah melewati 3 pesan gratis. Upgrade ke SkinCek Pro untuk melanjutkan chat.');
            }

            $message = $locked->messages()->create([
                'sender_id' => $user->id,
                'content' => $validated['content'] ?? null,
                'type' => $type,
            ]);

            if ($hasMedia) {
                $message->addMedia(ImageExifStripper::strip($mediaFile))->toMediaCollection('chat-media');
            }

            if ($isUserSender) {
                $locked->increment('message_count');
                $user->increment('user_messages_count');
            }

            return $message;
        });

        MessageSent::dispatch($message->load(['sender.roles', 'conversation']));

        if ($recipientIsAiBot) {
            GenerateAiReply::dispatch($conversation, $message);
            $this->incrementAiChatUsage($user);

            return (new MessageResource($message->load('sender.roles')))->response()->setStatusCode(201);
        }

        $recipient = $isUserSender ? $conversation->doctor : $conversation->user;
        Notification::send($recipient, new AppNotification(
            'Pesan baru dari '.$user->full_name,
            mb_strimwidth(strip_tags((string) ($validated['content'] ?? 'Foto')), 0, 120, '…'),
            ['conversation_id' => $conversation->uuid],
        ));

        $this->notifyOfflineRecipient($recipient, $user, $conversation, $validated['content'] ?? null);

        return (new MessageResource($message->load('sender.roles')))->response()->setStatusCode(201);
    }

    private function assertWithinAiChatQuota(User $user): void
    {
        if ($user->hasActiveSubscription()) {
            return;
        }

        $limit = (int) config('ai.free_daily_limit', 10);
        $count = (int) Cache::get($this->aiChatQuotaKey($user), 0);

        if ($count >= $limit) {
            abort(429, "Kamu sudah mencapai {$limit} pesan Aura Skin gratis hari ini. Upgrade ke SkinCek Pro untuk chat tanpa batas.");
        }
    }

    private function incrementAiChatUsage(User $user): void
    {
        if ($user->hasActiveSubscription()) {
            return;
        }

        $key = $this->aiChatQuotaKey($user);

        Cache::add($key, 0, now()->endOfDay());
        Cache::increment($key);
    }

    private function aiChatQuotaKey(User $user): string
    {
        return 'ai-chat-count:'.$user->id.':'.now()->toDateString();
    }

    private function notifyOfflineRecipient(User $recipient, User $sender, Conversation $conversation, ?string $content): void
    {
        $recentlyActive = $recipient->tokens()
            ->where('last_used_at', '>=', now()->subMinutes(5))
            ->exists();

        if ($recentlyActive) {
            return;
        }

        Mail::to($recipient)->send(new MessageNotificationMail(
            $sender->full_name,
            mb_strimwidth(strip_tags((string) ($content ?? 'Foto')), 0, 120, '…'),
            $conversation->uuid,
        ));
    }

    private function assertCleanContent(?string $content): void
    {
        if ($content === null || trim($content) === '') {
            return;
        }

        $badWords = config('chat.bad_words', []);

        if ($badWords === []) {
            return;
        }

        $lower = mb_strtolower($content);

        foreach ($badWords as $word) {
            if ($word !== '' && mb_strpos($lower, mb_strtolower($word)) !== false) {
                abort(422, 'Pesan mengandung kata yang tidak pantas');
            }
        }
    }

    private function assertParticipant(User $user, Conversation $conversation): void
    {
        abort_unless(
            $conversation->user_id === $user->id || $conversation->doctor_id === $user->id,
            404
        );
    }
}
