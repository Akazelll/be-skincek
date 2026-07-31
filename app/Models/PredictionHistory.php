<?php

namespace App\Models;

use App\Traits\HasPublicUuid;
use App\Enums\ScanMode;
use App\Enums\SeverityLevel;
use Illuminate\Database\Eloquent\Model;

class PredictionHistory extends Model
{
    use HasPublicUuid;

    const UPDATED_AT = null;

    protected $hidden = ['id'];
    protected $guarded = ['id'];

    protected $casts = [
        'scan_mode' => ScanMode::class,
        'severity_level' => SeverityLevel::class,
        'confidence' => 'decimal:4',
        'probabilities' => 'array',
        'raw_response' => 'array',
    ];
}
