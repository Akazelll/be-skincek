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
            ->with('doctorVerification')
            ->latest()
            ->paginate(15);

        return DoctorResource::collection($doctors);
    }

    public function show(Request $request, User $doctor)
    {
        $verification = $doctor->doctorVerification;

        abort_unless(
            $verification && $verification->verification_status === VerificationStatus::APPROVED,
            404
        );

        return new DoctorResource($doctor->load('doctorVerification'));
    }
}
