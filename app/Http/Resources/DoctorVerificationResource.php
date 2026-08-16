<?php

namespace App\Http\Resources;

use App\Support\MediaHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorVerificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'str_number' => $this->str_number,
            'title' => $this->title,
            'specialization' => $this->specialization,
            'sub_specialization' => $this->sub_specialization,
            'experience_years' => $this->experience_years,
            'alma_mater' => $this->alma_mater,
            'practice_locations' => $this->practice_locations,
            'professional_organizations' => $this->professional_organizations,
            'verification_status' => $this->verification_status->value,
            'rejection_reason' => $this->rejection_reason,
            'revision_note' => $this->revision_note,
            'documents' => $this->getMedia('verification-document')->map(fn ($media) => [
                'uuid' => $media->uuid,
                'url' => MediaHelper::url($media),
                'file_name' => $media->file_name,
            ]),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
