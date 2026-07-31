<?php

namespace App\Models;

use App\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasPublicUuid; 

    protected $hidden = ['id']; 
    protected $guarded = ['id']; 
}
