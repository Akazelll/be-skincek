<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasPublicUuid;

    protected $hidden = ['id'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
