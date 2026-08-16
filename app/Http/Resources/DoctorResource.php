<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $verification = $this->doctorVerification;

        $data = [
            'uuid' => $this->uuid,
            'full_name' => $this->full_name,
            'title' => $verification?->title,
            'specialization' => $verification?->specialization,
            'avatar' => $this->getFirstMediaUrl('avatar'),
        ];

        if ($request->routeIs('doctors.show')) {
            $data['sub_specialization'] = $verification?->sub_specialization;
            $data['str_number'] = $verification?->str_number;
            $data['experience_years'] = $verification?->experience_years;
            $data['alma_mater'] = $verification?->alma_mater;
            $data['practice_locations'] = $verification?->practice_locations ?? [];
            $data['professional_organizations'] = $verification?->professional_organizations ?? [];
        }

        return $data;
    }
}
