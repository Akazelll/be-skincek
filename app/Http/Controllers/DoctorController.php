<?php

namespace App\Http\Controllers;

use App\Enums\VerificationStatus;
use App\Http\Resources\DoctorResource;
use App\Models\User;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
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
