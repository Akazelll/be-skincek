<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiChatConsent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
