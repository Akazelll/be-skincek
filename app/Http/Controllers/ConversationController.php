<?php

namespace App\Http\Controllers;

use App\Enums\VerificationStatus;
use App\Events\MessageSent;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\DoctorVerification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            ->with(['user.roles', 'doctor.roles', 'messages' => fn ($query) => $query->with('sender.roles')->latest()->limit(1)])
            ->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('doctor_id', $user->id))
            ->latest()
            ->paginate($this->perPage($request));

        return ConversationResource::collection($conversations);
    }

    public function messages(Request $request, Conversation $conversation)
    {
        $this->assertParticipant($request->user(), $conversation);

        $messages = $conversation->messages()
            ->with('sender.roles')
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

        $hasMedia = $request->hasFile('media');
        $mediaFile = $hasMedia ? $request->file('media') : null;
        $type = 'text';

        if ($hasMedia) {
            $mime = $mediaFile->getMimeType();
            $type = str_starts_with($mime, 'video/') ? 'video' : 'image';
        }

        $isUserSender = $conversation->user_id === $user->id;

        $message = DB::transaction(function () use ($conversation, $user, $validated, $isUserSender, $hasMedia, $mediaFile, $type) {
            $locked = Conversation::whereKey($conversation->getKey())->lockForUpdate()->first();

            if ($isUserSender && $user->hasReachedFreeChatQuota()) {
                abort(402, 'Kamu sudah melewati 3 pesan gratis. Upgrade ke SkinCek Pro untuk melanjutkan chat.');
            }

            $message = $locked->messages()->create([
                'sender_id' => $user->id,
                'content' => $validated['content'] ?? null,
                'type' => $type,
            ]);

            if ($hasMedia) {
                $message->addMedia($mediaFile)->toMediaCollection('chat-media');
            }

            if ($isUserSender) {
                $locked->increment('message_count');
                $user->increment('user_messages_count');
            }

            return $message;
        });

        MessageSent::dispatch($message->load(['sender.roles', 'conversation']));

        return (new MessageResource($message->load('sender.roles')))->response()->setStatusCode(201);
    }

    private function assertParticipant(User $user, Conversation $conversation): void
    {
        abort_unless(
            $conversation->user_id === $user->id || $conversation->doctor_id === $user->id,
            404
        );
    }
}
