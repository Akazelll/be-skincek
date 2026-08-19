<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'role' => $this->roles->first()?->name,
            'avatar_url' => $this->avatarUrl(),
            'is_active' => $this->is_active,

            'gender' => $this->gender?->value,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'age' => $this->date_of_birth?->age,
            
            'doctor_verification' => DoctorVerificationResource::make($this->whenLoaded('doctorVerification')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
