<?php

use App\Models\DoctorVerification;
use App\Models\PredictionHistory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Menyelaraskan nama collection Media Library dengan PRD §7.2 & §7.7:
     * - doctor_verifications: documents -> verification-document
     * - prediction_histories: image -> scan-photo (upload) / scan-photo-cropped (livecam)
     */
    public function up(): void
    {
        DB::table('media')
            ->where('model_type', PredictionHistory::class)
            ->where('collection_name', 'image')
            ->whereIn('model_id', fn ($q) => $q->select('id')->from('prediction_histories')->where('scan_mode', 'livecam'))
            ->update(['collection_name' => 'scan-photo-cropped']);

        DB::table('media')
            ->where('model_type', PredictionHistory::class)
            ->where('collection_name', 'image')
            ->update(['collection_name' => 'scan-photo']);

        DB::table('media')
            ->where('model_type', DoctorVerification::class)
            ->where('collection_name', 'documents')
            ->update(['collection_name' => 'verification-document']);
    }

    public function down(): void
    {
        DB::table('media')
            ->where('model_type', PredictionHistory::class)
            ->where('collection_name', 'scan-photo-cropped')
            ->update(['collection_name' => 'image']);

        DB::table('media')
            ->where('model_type', PredictionHistory::class)
            ->where('collection_name', 'scan-photo')
            ->update(['collection_name' => 'image']);

        DB::table('media')
            ->where('model_type', DoctorVerification::class)
            ->where('collection_name', 'verification-document')
            ->update(['collection_name' => 'documents']);
    }
};
