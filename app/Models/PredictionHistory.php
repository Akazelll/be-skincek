<?php

namespace App\Models;

use App\Enums\ScanMode;
use App\Enums\SeverityLevel;
use App\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class PredictionHistory extends Model implements HasMedia
{
    use HasPublicUuid, InteractsWithMedia;

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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('scan-photo')->singleFile();
        $this->addMediaCollection('scan-photo-cropped')->singleFile();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function feedbacks()
    {
        return $this->hasMany(PredictionFeedback::class);
    }

    public function skinConcern()
    {
        return $this->belongsTo(SkinConcern::class, 'predicted_class', 'ml_label');
    }
}
