<?php

namespace App\Http\Controllers;

use App\Enums\VerificationStatus;
use App\Events\DoctorVerificationReviewed;
use App\Http\Resources\DoctorVerificationResource;
use App\Models\DoctorVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DoctorVerificationController extends Controller
{
    public function index(Request $request)
    {
        $verifications = DoctorVerification::with('doctor')
            ->when($request->input('status'), function ($query, $status) {
                $query->where('verification_status', $status);
            })
            ->latest()
            ->paginate(15);

        return DoctorVerificationResource::collection($verifications);
    }

    public function show(Request $request)
    {
        $verification = $request->user()->doctorVerification;

        if (! $verification) {
            return $this->errorResponse('Belum ada pengajuan verifikasi', 404);
        }

        return new DoctorVerificationResource($verification);
    }

    public function submit(Request $request)
    {
        $user = $request->user();
        $existing = $user->doctorVerification;

        if ($existing && $existing->verification_status !== VerificationStatus::REJECTED) {
            return $this->errorResponse('Pengajuan verifikasi sudah ada dan masih diproses', 422);
        }

        $validated = $request->validate([
            'str_number' => ['nullable', 'string', 'max:50'],
            'specialization' => ['required', 'string', 'max:255'],
            'documents.*' => ['required', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
        ]);

        $verification = DB::transaction(function () use ($user, $existing, $validated, $request) {
            if ($existing) {
                $existing->update([
                    'str_number' => $validated['str_number'] ?? null,
                    'specialization' => $validated['specialization'],
                    'verification_status' => VerificationStatus::PENDING,
                    'rejection_reason' => null,
                    'revision_note' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ]);
                $existing->clearMediaCollection('documents');
                $verification = $existing;
            } else {
                $verification = $user->doctorVerification()->create([
                    'str_number' => $validated['str_number'] ?? null,
                    'specialization' => $validated['specialization'],
                    'verification_status' => VerificationStatus::PENDING,
                ]);
            }

            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $verification->addMedia($file)->toMediaCollection('documents');
                }
            }

            return $verification;
        });

        return (new DoctorVerificationResource($verification))->response()->setStatusCode(201);
    }

    public function resubmit(Request $request)
    {
        $user = $request->user();
        $verification = $user->doctorVerification;

        if (! $verification || $verification->verification_status !== VerificationStatus::NEEDS_REVISION) {
            return $this->errorResponse('Verifikasi tidak dapat diajukan ulang', 422);
        }

        $validated = $request->validate([
            'str_number' => ['nullable', 'string', 'max:50'],
            'specialization' => ['sometimes', 'string', 'max:255'],
            'documents.*' => ['sometimes', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
        ]);

        DB::transaction(function () use ($verification, $validated, $request) {
            $update = array_filter([
                'str_number' => $validated['str_number'] ?? null,
                'specialization' => $validated['specialization'] ?? null,
            ], fn ($v) => $v !== null);

            $update['verification_status'] = VerificationStatus::PENDING;
            $update['revision_note'] = null;
            $update['reviewed_by'] = null;
            $update['reviewed_at'] = null;

            $verification->update($update);

            if ($request->hasFile('documents')) {
                $verification->clearMediaCollection('documents');
                foreach ($request->file('documents') as $file) {
                    $verification->addMedia($file)->toMediaCollection('documents');
                }
            }
        });

        return new DoctorVerificationResource($verification->fresh());
    }

    public function review(Request $request, DoctorVerification $doctorVerification)
    {
        if ($doctorVerification->verification_status !== VerificationStatus::PENDING) {
            return $this->errorResponse('Verifikasi sudah diproses', 422);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                VerificationStatus::APPROVED->value,
                VerificationStatus::REJECTED->value,
                VerificationStatus::NEEDS_REVISION->value,
            ])],
            'rejection_reason' => ['required_if:status,rejected', 'string', 'nullable'],
            'revision_note' => ['required_if:status,needs_revision', 'string', 'nullable'],
        ]);

        $status = VerificationStatus::from($validated['status']);

        $doctorVerification->update([
            'verification_status' => $status,
            'rejection_reason' => $status === VerificationStatus::REJECTED ? $validated['rejection_reason'] ?? null : null,
            'revision_note' => $status === VerificationStatus::NEEDS_REVISION ? $validated['revision_note'] ?? null : null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        DoctorVerificationReviewed::dispatch($doctorVerification->fresh());

        return new DoctorVerificationResource($doctorVerification->fresh());
    }
}
