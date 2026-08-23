<?php

namespace App\Http\Controllers;

use App\Http\Resources\SkinConcernResource;
use App\Models\SkinConcern;
use Illuminate\Http\Request;

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

    public function show(SkinConcern $skinConcern)
    {
        return new SkinConcernResource($skinConcern);
    }

    public function update(Request $request, SkinConcern $skinConcern)
    {
        $validated = $request->validate([
            'description' => ['required', 'string'],
        ]);

        $skinConcern->update($validated);

        return new SkinConcernResource($skinConcern->fresh());
    }
}
