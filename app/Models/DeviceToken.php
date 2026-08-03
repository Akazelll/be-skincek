<?php

namespace App\Models;

use App\Enums\DevicePlatform;
use App\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    use HasPublicUuid;

    protected $hidden = ['id'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['platform' => DevicePlatform::class];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
