<?php

namespace App\Models;

use App\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Message extends Model implements HasMedia
{
    use HasPublicUuid;
    use InteractsWithMedia;

    const UPDATED_AT = null;

    protected $hidden = ['id'];

    protected $guarded = ['id'];

    protected $casts = [
        'content' => 'encrypted',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('chat-media');
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
