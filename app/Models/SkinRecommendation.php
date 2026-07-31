<?php

namespace App\Models;

use App\Enums\PriorityLevel;
use App\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class SkinRecommendation extends Model
{
    use HasPublicUuid; 

    protected $hidden = ['id']; 
    protected $guarded = ['id']; 

    protected function casts(): array
    {
        return [
            'priority_level' => PriorityLevel::class, 
            'is_active' => 'boolean', 
        ];
    }
}
