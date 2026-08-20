<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictionFeedback extends Model
{
    use HasFactory;

    protected $table = 'prediction_feedbacks';

    protected $guarded = ['id'];

    public function predictionHistory(): BelongsTo
    {
        return $this->belongsTo(PredictionHistory::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
