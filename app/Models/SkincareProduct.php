<?php

namespace App\Models;

use App\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class SkincareProduct extends Model
{
    use HasPublicUuid;

    protected $hidden = ['id'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function concern()
    {
        return $this->belongsTo(SkinConcern::class, 'concern_id');
    }

    public function skinType()
    {
        return $this->belongsTo(SkinType::class);
    }
}
