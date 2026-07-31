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
}
