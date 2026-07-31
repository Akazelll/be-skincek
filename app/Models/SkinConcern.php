<?php

namespace App\Models;

use App\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class SkinConcern extends Model
{
    use HasPublicUuid;

    protected $hidden = ['id'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function products()
    {
        return $this->hasMany(SkincareProduct::class, 'concern_id');
    }

    public function recommendations()
    {
        return $this->hasMany(SkinRecommendation::class, 'concern_id');
    }
}
