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

    public function concern()
    {
        return $this->belongsTo(SkinConcern::class, 'concern_id');
    }

    public function product()
    {
        return $this->belongsTo(SkincareProduct::class, 'product_id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }
}
