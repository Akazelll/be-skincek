<?php

namespace App\Http\Controllers;

use App\Enums\VerificationStatus;
use App\Http\Resources\DoctorResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\SkincareProduct;
use App\Models\SkinRecommendation;
use App\Models\User;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    public function dashboard(Request $request)
    {
        $doctor = $request->user();

        $conversationIds = Conversation::where('doctor_id', $doctor->id)->pluck('id');

        $awaitingReply = 0;

        if ($conversationIds->isNotEmpty()) {
            $latestPerConversation = Message::whereIn('conversation_id', $conversationIds)
                ->selectRaw('MAX(id) as max_id')
                ->groupBy('conversation_id')
                ->pluck('max_id');

            $awaitingReply = Message::whereIn('id', $latestPerConversation)
                ->where('sender_id', '!=', $doctor->id)
                ->count();
        }

        $recentConversations = Conversation::where('doctor_id', $doctor->id)
            ->with(['user.roles', 'user.media'])
            ->with(['messages' => fn ($query) => $query->latest('id')->limit(1)->with('sender.roles')])
            ->withCount('messages as message_count')
            ->when($conversationIds->isNotEmpty(), fn ($query) => $query
                ->addSelect([
                    'last_message_at' => Message::select('created_at')
                        ->whereColumn('conversation_id', 'conversations.id')
                        ->latest('id')
                        ->limit(1),
                ])
                ->orderByDesc('last_message_at'))
            ->limit(5)
            ->get();

        return $this->successResponse([
            'verification_status' => $doctor->doctorVerification?->verification_status->value,
            'stats' => [
                'total_patients' => Conversation::where('doctor_id', $doctor->id)->distinct('user_id')->count('user_id'),
                'conversations_awaiting_reply' => $awaitingReply,
                'my_products' => SkincareProduct::where('doctor_id', $doctor->id)->count(),
                'my_recommendations' => SkinRecommendation::where('doctor_id', $doctor->id)->count(),
                'average_rating' => round((float) $doctor->doctorRatings()->avg('rating'), 2),
                'total_ratings' => $doctor->doctorRatings()->count(),
            ],
            'recent_conversations' => $recentConversations->map(fn (Conversation $conversation) => [
                'uuid' => $conversation->uuid,
                'user' => $conversation->user ? [
                    'uuid' => $conversation->user->uuid,
                    'full_name' => $conversation->user->full_name,
                ] : null,
                'message_count' => $conversation->message_count,
                'last_message' => $conversation->messages->first() ? [
                    'content' => $conversation->messages->first()->type === 'scan_result'
                        ? '[Hasil Scan]'
                        : substr($conversation->messages->first()->content, 0, 100),
                    'sender_role' => $conversation->messages->first()->sender?->roles->first()?->name,
                    'created_at' => $conversation->messages->first()->created_at?->toISOString(),
                ] : null,
            ])->values(),
        ]);
    }

    public function index(Request $request)
    {
        $doctors = User::query()
            ->role('doctor')
            ->whereHas('doctorVerification', fn ($query) => $query
                ->where('verification_status', VerificationStatus::APPROVED))
            ->with(['doctorVerification', 'media'])
            ->withCount(['doctorRatings as doctor_rating_count'])
            ->withAvg(['doctorRatings as doctor_rating_avg'], 'rating')
            ->orderByRaw('ai_bot DESC')
            ->latest()
            ->paginate($this->perPage($request));

        return DoctorResource::collection($doctors);
    }

    public function show(Request $request, User $doctor)
    {
        $doctor->load('doctorVerification');

        abort_unless(
            $doctor->doctorVerification && $doctor->doctorVerification->verification_status === VerificationStatus::APPROVED,
            404
        );

        $doctor->loadCount(['doctorRatings as doctor_rating_count']);
        $doctor->loadAvg(['doctorRatings as doctor_rating_avg'], 'rating');

        return new DoctorResource($doctor);
    }
}
