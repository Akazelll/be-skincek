<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use App\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class DoctorVerification extends Model
{
    use HasPublicUuid; 

    protected $hidden = ['id']; 
    protected $guarded = ['id']; 

    protected function casts(): array
    {
        return [
            'verification_status' => VerificationStatus::class, 
            'reviewed_at' => 'datetime', 
        ];
    }
}
