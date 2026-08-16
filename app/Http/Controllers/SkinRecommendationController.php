<?php

namespace App\Http\Controllers;

use App\Http\Resources\SkinRecommendationResource;
use App\Models\SkinConcern;
use App\Models\SkinRecommendation;
use Illuminate\Http\Request;

class SkinRecommendationController extends Controller
{
    public function index(Request $request)
    {
        $recommendations = SkinRecommendation::with(['concern', 'product', 'doctor'])
            ->where('is_active', true)
            ->when($request->input('ml_label'), function ($query, $mlLabel) {
                $concern = SkinConcern::where('ml_label', $mlLabel)->first();
                $query->where('concern_id', $concern?->id ?? 0);
            })
            ->when($request->input('concern_id'), fn ($q, $id) => $q->where('concern_id', $id))
            ->orderByRaw("CASE priority_level WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->latest()
            ->paginate($this->perPage($request));

        return SkinRecommendationResource::collection($recommendations);
    }

    public function doctorIndex(Request $request)
    {
        $recommendations = $request->user()->skinRecommendations()
            ->with(['concern', 'product', 'doctor'])
            ->latest()
            ->paginate($this->perPage($request));

        return SkinRecommendationResource::collection($recommendations);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isVerifiedDoctor(), 403, 'Dokter harus terverifikasi terlebih dahulu');

        $validated = $request->validate([
            'concern_id' => ['required', 'exists:skin_concerns,id'],
            'product_id' => ['nullable', 'exists:skincare_products,id'],
            'title' => ['required', 'string', 'max:255'],
            'recommendation_text' => ['required', 'string'],
            'priority_level' => ['sometimes', 'in:low,medium,high'],
            'is_active' => ['boolean'],
        ]);

        $recommendation = $request->user()->skinRecommendations()->create($validated);

        return new SkinRecommendationResource($recommendation->load(['concern', 'product', 'doctor']));
    }

    public function show(SkinRecommendation $skinRecommendation)
    {
        return new SkinRecommendationResource($skinRecommendation->load(['concern', 'product', 'doctor']));
    }

    public function update(Request $request, SkinRecommendation $skinRecommendation)
    {
        abort_unless($skinRecommendation->doctor_id === $request->user()->id || $request->user()->hasRole('admin'), 403);

        $validated = $request->validate([
            'concern_id' => ['sometimes', 'exists:skin_concerns,id'],
            'product_id' => ['nullable', 'exists:skincare_products,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'recommendation_text' => ['sometimes', 'string'],
            'priority_level' => ['sometimes', 'in:low,medium,high'],
            'is_active' => ['boolean'],
        ]);

        $skinRecommendation->update($validated);

        return new SkinRecommendationResource($skinRecommendation->fresh()->load(['concern', 'product', 'doctor']));
    }

    public function destroy(Request $request, SkinRecommendation $skinRecommendation)
    {
        abort_unless($skinRecommendation->doctor_id === $request->user()->id || $request->user()->hasRole('admin'), 403);

        $skinRecommendation->delete();

        return $this->successResponse(null, ['message' => 'Rekomendasi berhasil dihapus']);
    }
}
