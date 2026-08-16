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
            'title' => ['nullable', 'string', 'max:100'],
            'sub_specialization' => ['nullable', 'string', 'max:255'],
            'experience_years' => ['nullable', 'integer', 'between:0,100'],
            'alma_mater' => ['nullable', 'string', 'max:255'],
            'practice_locations' => ['nullable', 'array', 'max:20'],
            'practice_locations.*' => ['string', 'max:255'],
            'professional_organizations' => ['nullable', 'array', 'max:20'],
            'professional_organizations.*' => ['string', 'max:100'],
            'documents.*' => ['required', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
        ]);

        $verification = DB::transaction(function () use ($user, $existing, $validated, $request) {
            if ($existing) {
                $existing->update([
                    'str_number' => $validated['str_number'] ?? null,
                    'specialization' => $validated['specialization'],
                    'title' => $validated['title'] ?? null,
                    'sub_specialization' => $validated['sub_specialization'] ?? null,
                    'experience_years' => $validated['experience_years'] ?? null,
                    'alma_mater' => $validated['alma_mater'] ?? null,
                    'practice_locations' => $validated['practice_locations'] ?? [],
                    'professional_organizations' => $validated['professional_organizations'] ?? [],
                    'verification_status' => VerificationStatus::PENDING,
                    'rejection_reason' => null,
                    'revision_note' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ]);
                $existing->clearMediaCollection('verification-document');
                $verification = $existing;
            } else {
                $verification = $user->doctorVerification()->create([
                    'str_number' => $validated['str_number'] ?? null,
                    'specialization' => $validated['specialization'],
                    'title' => $validated['title'] ?? null,
                    'sub_specialization' => $validated['sub_specialization'] ?? null,
                    'experience_years' => $validated['experience_years'] ?? null,
                    'alma_mater' => $validated['alma_mater'] ?? null,
                    'practice_locations' => $validated['practice_locations'] ?? [],
                    'professional_organizations' => $validated['professional_organizations'] ?? [],
                    'verification_status' => VerificationStatus::PENDING,
                ]);
            }

            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $verification->addMedia($file)->toMediaCollection('verification-document');
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
            'title' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sub_specialization' => ['sometimes', 'nullable', 'string', 'max:255'],
            'experience_years' => ['sometimes', 'nullable', 'integer', 'between:0,100'],
            'alma_mater' => ['sometimes', 'nullable', 'string', 'max:255'],
            'practice_locations' => ['sometimes', 'nullable', 'array', 'max:20'],
            'practice_locations.*' => ['string', 'max:255'],
            'professional_organizations' => ['sometimes', 'nullable', 'array', 'max:20'],
            'professional_organizations.*' => ['string', 'max:100'],
            'documents.*' => ['sometimes', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
        ]);

        DB::transaction(function () use ($verification, $validated, $request) {
            $update = array_filter([
                'str_number' => $validated['str_number'] ?? null,
                'specialization' => $validated['specialization'] ?? null,
                'title' => $validated['title'] ?? null,
                'sub_specialization' => $validated['sub_specialization'] ?? null,
                'experience_years' => $validated['experience_years'] ?? null,
                'alma_mater' => $validated['alma_mater'] ?? null,
                'practice_locations' => $validated['practice_locations'] ?? null,
                'professional_organizations' => $validated['professional_organizations'] ?? null,
            ], fn ($v) => $v !== null);

            $update['verification_status'] = VerificationStatus::PENDING;
            $update['revision_note'] = null;
            $update['reviewed_by'] = null;
            $update['reviewed_at'] = null;

            $verification->update($update);

            if ($request->hasFile('documents')) {
                $verification->clearMediaCollection('verification-document');
                foreach ($request->file('documents') as $file) {
                    $verification->addMedia($file)->toMediaCollection('verification-document');
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

        activity()
            ->useLog('doctor_verification_review')
            ->performedOn($doctorVerification)
            ->causedBy($request->user())
            ->withProperties([
                'verification_status' => $status->value,
                'rejection_reason' => $doctorVerification->rejection_reason,
                'revision_note' => $doctorVerification->revision_note,
            ])
            ->log('Doctor verification reviewed');

        return new DoctorVerificationResource($doctorVerification->fresh());
    }
}
