<?php

namespace App\Http\Controllers;

use App\Http\Resources\SkinTypeResource;
use App\Models\SkinType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SkinTypeController extends Controller
{
    public function index()
    {
        return SkinTypeResource::collection(
            SkinType::where('is_active', true)->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:skin_types,name'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        return new SkinTypeResource(SkinType::create($validated));
    }

    public function show(SkinType $skinType)
    {
        return new SkinTypeResource($skinType);
    }

    public function update(Request $request, SkinType $skinType)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('skin_types', 'name')->ignore($skinType->id)],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $skinType->update($validated);

        return new SkinTypeResource($skinType->fresh());
    }

    public function destroy(SkinType $skinType)
    {
        $skinType->delete();

        return $this->successResponse(null, ['message' => 'Skin type berhasil dihapus']);
    }
}
