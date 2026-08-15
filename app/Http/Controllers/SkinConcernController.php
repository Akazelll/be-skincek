<?php

namespace App\Http\Controllers;

use App\Http\Resources\SkinConcernResource;
use App\Models\SkinConcern;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SkinConcernController extends Controller
{
    public function index(Request $request)
    {
        $concerns = SkinConcern::query()
            ->when(! $request->user()?->hasRole('admin'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();

        return SkinConcernResource::collection($concerns);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:skin_concerns,name'],
            'ml_label' => ['required', 'string', 'max:255', 'unique:skin_concerns,ml_label'],
            'description' => ['nullable', 'string'],
            'default_severity_score' => ['nullable', 'integer', 'between:0,100'],
            'is_active' => ['boolean'],
        ]);

        return new SkinConcernResource(SkinConcern::create($validated));
    }

    public function show(SkinConcern $skinConcern)
    {
        return new SkinConcernResource($skinConcern);
    }

    public function update(Request $request, SkinConcern $skinConcern)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('skin_concerns', 'name')->ignore($skinConcern->id)],
            'ml_label' => ['sometimes', 'string', 'max:255', Rule::unique('skin_concerns', 'ml_label')->ignore($skinConcern->id)],
            'description' => ['nullable', 'string'],
            'default_severity_score' => ['nullable', 'integer', 'between:0,100'],
            'is_active' => ['boolean'],
        ]);

        $skinConcern->update($validated);

        return new SkinConcernResource($skinConcern->fresh());
    }

    public function destroy(SkinConcern $skinConcern)
    {
        $skinConcern->delete();

        return $this->successResponse(null, ['message' => 'Skin concern berhasil dihapus']);
    }
}
