<?php

namespace App\Http\Controllers;

use App\Enums\VerificationStatus;
use App\Models\Conversation;
use App\Models\DoctorRating;
use App\Models\User;
use Illuminate\Http\Request;

class DoctorRatingController extends Controller
{
    public function store(Request $request, User $doctor)
    {
        abort_unless($this->isApprovedDoctor($doctor), 404);

        $hasConversation = Conversation::where('doctor_id', $doctor->id)
            ->where('user_id', $request->user()->id)
            ->exists();

        abort_unless($hasConversation, 422, 'Kamu hanya dapat menilai dokter yang pernah kamu ajak chat');

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'review' => ['nullable', 'string', 'max:1000'],
        ]);

        $rating = DoctorRating::updateOrCreate(
            ['user_id' => $request->user()->id, 'doctor_id' => $doctor->id],
            ['rating' => $validated['rating'], 'review' => $validated['review'] ?? null]
        );

        return $this->successResponse([
            'rating' => $rating->rating,
            'review' => $rating->review,
        ], ['message' => 'Rating dokter berhasil disimpan'], 201);
    }

    public function index(Request $request, User $doctor)
    {
        abort_unless($this->isApprovedDoctor($doctor), 404);

        $ratings = DoctorRating::with('user')
            ->where('doctor_id', $doctor->id)
            ->latest()
            ->paginate($this->perPage($request));

        $ratings->getCollection()->transform(fn ($r) => [
            'rating' => $r->rating,
            'review' => $r->review,
            'user' => $r->user ? ['uuid' => $r->user->uuid, 'full_name' => $r->user->full_name] : null,
            'created_at' => $r->created_at?->toISOString(),
        ]);

        return response()->json([
            'data' => $ratings->items(),
            'meta' => [
                'current_page' => $ratings->currentPage(),
                'last_page' => $ratings->lastPage(),
                'per_page' => $ratings->perPage(),
                'total' => $ratings->total(),
            ],
        ]);
    }

    private function isApprovedDoctor(User $doctor): bool
    {
        return $doctor->hasRole('doctor')
            && $doctor->doctorVerification?->verification_status === VerificationStatus::APPROVED;
    }
}
