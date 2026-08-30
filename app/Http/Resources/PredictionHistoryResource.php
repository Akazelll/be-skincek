<?php

namespace App\Http\Resources;

use App\Models\SkinConcern;
use App\Support\MediaHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PredictionHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $threshold = (float) config('services.ml.confidence_threshold', 0.50);
        $concernThreshold = (float) config('services.ml.concern_description_threshold', 0.10);

        $skinConcern = $this->skinConcern;

        return [
            'uuid' => $this->uuid,
            'scan_mode' => $this->scan_mode->value,
            'predicted_class' => $this->predicted_class,
            'confidence' => (float) $this->confidence,
            'probabilities' => $this->probabilities,
            'severity_score' => $this->severity_score,
            'severity_level' => $this->severity_level->value,
            'model_used' => $this->model_used,
            'image_url' => MediaHelper::url(
                $this->getFirstMedia('scan-photo') ?? $this->getFirstMedia('scan-photo-cropped')
            ),
            'disclaimer' => config('services.ml.disclaimer'),
            'skin_concern' => $skinConcern ? [
                'name' => $skinConcern->name,
                'description' => $skinConcern->description,
            ] : null,
            'other_concerns' => $this->buildOtherConcerns($concernThreshold),
            'treatment_recommendations' => $this->buildTreatmentRecommendations($skinConcern),
            'skincare_recommendations' => $this->buildSkincareRecommendations($skinConcern),
            'notice' => (float) $this->confidence < $threshold
                ? 'Hasil prediksi ini memiliki tingkat keyakinan rendah. Sebaiknya lakukan scan ulang dengan pencahayaan yang lebih baik atau konsultasikan langsung dengan dokter kulit.'
                : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Rekomendasi PERAWATAN = TIPS (tabel skin_recommendations).
     * Bukan produk — untuk produk lihat buildSkincareRecommendations().
     */
    private function buildTreatmentRecommendations(?SkinConcern $skinConcern): array
    {
        if (! $skinConcern) {
            return [];
        }

        $recommendations = $skinConcern->recommendations()
            ->where('is_active', true)
            ->get();

        $priorityOrder = ['high' => 0, 'medium' => 1, 'low' => 2];

        return $recommendations
            ->sortBy(fn ($recommendation) => $priorityOrder[$recommendation->priority_level->value] ?? 3)
            ->map(fn ($recommendation) => [
                'uuid' => $recommendation->uuid,
                'title' => $recommendation->title,
                'recommendation_text' => $recommendation->recommendation_text,
                'priority_level' => $recommendation->priority_level->value,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Rekomendasi SKINCARE = PRODUK (tabel skincare_products).
     * Bukan tips — untuk tips lihat buildTreatmentRecommendations().
     */
    private function buildSkincareRecommendations(?SkinConcern $skinConcern): array
    {
        if (! $skinConcern) {
            return [];
        }

        $userGender = $this->user?->gender?->value;

        return $skinConcern->products()
            ->where('is_active', true)
            ->with(['doctor', 'skinType'])
            ->when($userGender, fn ($query) => $query->where(function ($q) use ($userGender) {
                $q->where('gender', 'unisex')->orWhere('gender', $userGender);
            }))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn ($product) => [
                'uuid' => $product->uuid,
                'name' => $product->name,
                'category' => $product->category,
                'gender' => $product->gender?->value ?? 'unisex',
                'key_ingredients' => $product->key_ingredients,
                'usage_instruction' => $product->usage_instruction,
                'warning' => $product->warning,
                'skin_type' => $product->skinType?->name,
                'doctor' => $product->doctor?->full_name,
            ])
            ->values()
            ->toArray();
    }

    private function buildOtherConcerns(float $threshold): array
    {
        $probabilities = $this->probabilities ?? [];

        if (empty($probabilities)) {
            return [];
        }

        $mlLabels = array_keys($probabilities);
        $concerns = SkinConcern::whereIn('ml_label', $mlLabels)->get()->keyBy('ml_label');

        $otherConcerns = [];

        foreach ($probabilities as $label => $confidence) {
            if ($label === $this->predicted_class) {
                continue;
            }

            if ((float) $confidence < $threshold) {
                continue;
            }

            $concern = $concerns->get($label);

            $otherConcerns[] = [
                'ml_label' => $label,
                'name' => $concern?->name,
                'description' => $concern?->description,
                'confidence' => (float) $confidence,
            ];
        }

        usort($otherConcerns, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);

        return $otherConcerns;
    }
}
