<?php

namespace App\Models;

use App\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class SkinType extends Model
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
        return $this->hasMany(SkincareProduct::class);
    }
}
