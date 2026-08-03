<?php

namespace App\Http\Controllers;

use App\Http\Resources\SkincareProductResource;
use App\Models\SkincareProduct;
use Illuminate\Http\Request;

class SkincareProductController extends Controller
{
    public function index(Request $request)
    {
        $products = SkincareProduct::with(['concern', 'skinType', 'doctor'])
            ->where('is_active', true)
            ->when($request->input('concern'), fn ($q, $concern) => $q->where('concern_id', $concern))
            ->when($request->input('skin_type'), fn ($q, $type) => $q->where('skin_type_id', $type))
            ->latest()
            ->paginate(15);

        return SkincareProductResource::collection($products);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isVerifiedDoctor(), 403, 'Dokter harus terverifikasi terlebih dahulu');

        $validated = $request->validate([
            'concern_id' => ['required', 'exists:skin_concerns,id'],
            'skin_type_id' => ['nullable', 'exists:skin_types,id'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'key_ingredients' => ['nullable', 'string'],
            'usage_instruction' => ['required', 'string'],
            'warning' => ['nullable', 'string'],
        ]);

        $product = $request->user()->skincareProducts()->create($validated);

        return new SkincareProductResource($product->load(['concern', 'skinType', 'doctor']));
    }

    public function show(SkincareProduct $skincareProduct)
    {
        return new SkincareProductResource($skincareProduct->load(['concern', 'skinType', 'doctor']));
    }

    public function adminIndex()
    {
        $products = SkincareProduct::with(['concern', 'skinType', 'doctor'])->latest()->paginate(15);

        return SkincareProductResource::collection($products);
    }

    public function update(Request $request, SkincareProduct $skincareProduct)
    {
        abort_unless($skincareProduct->doctor_id === $request->user()->id || $request->user()->hasRole('admin'), 403);

        $validated = $request->validate([
            'concern_id' => ['sometimes', 'exists:skin_concerns,id'],
            'skin_type_id' => ['nullable', 'exists:skin_types,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'string', 'max:255'],
            'key_ingredients' => ['nullable', 'string'],
            'usage_instruction' => ['sometimes', 'string'],
            'warning' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $skincareProduct->update($validated);

        return new SkincareProductResource($skincareProduct->fresh()->load(['concern', 'skinType', 'doctor']));
    }

    public function destroy(Request $request, SkincareProduct $skincareProduct)
    {
        abort_unless($skincareProduct->doctor_id === $request->user()->id || $request->user()->hasRole('admin'), 403);

        $skincareProduct->delete();

        return $this->successResponse(null, ['message' => 'Produk berhasil dihapus']);
    }
}
