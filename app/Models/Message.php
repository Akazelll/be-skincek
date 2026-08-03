<?php

namespace App\Models;

use App\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasPublicUuid;

    const UPDATED_AT = null;

    protected $hidden = ['id'];

    protected $guarded = ['id'];

    protected $casts = [
        'content' => 'encrypted',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
