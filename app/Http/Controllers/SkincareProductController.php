<?php

namespace App\Http\Controllers;

use App\Enums\ProductGender;
use App\Http\Resources\SkincareProductResource;
use App\Models\SkinConcern;
use App\Models\SkinType;
use App\Models\SkincareProduct;
use App\Support\UuidResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SkincareProductController extends Controller
{
    public function index(Request $request)
    {
        $products = SkincareProduct::with(['concern', 'skinType', 'doctor.roles'])
            ->where('is_active', true)
            ->when($request->input('concern'), fn ($q, $concern) => $q->where('concern_id', $concern))
            ->when($request->input('skin_type'), fn ($q, $type) => $q->where('skin_type_id', $type))
            ->when($request->input('gender'), fn ($q, $gender) => $q->where('gender', $gender))
            ->latest()
            ->paginate($this->perPage($request));

        return SkincareProductResource::collection($products);
    }

    public function doctorIndex(Request $request)
    {
        $products = $request->user()->skincareProducts()
            ->with(['concern', 'skinType', 'doctor.roles'])
            ->latest()
            ->paginate($this->perPage($request));

        return SkincareProductResource::collection($products);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isVerifiedDoctor(), 403, 'Dokter harus terverifikasi terlebih dahulu');

        $this->resolveRelationIdentifiers($request);

        $validated = $request->validate([
            'concern_id' => ['required', 'exists:skin_concerns,id'],
            'skin_type_id' => ['nullable', 'exists:skin_types,id'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', Rule::enum(ProductGender::class)],
            'key_ingredients' => ['nullable', 'string'],
            'usage_instruction' => ['required', 'string'],
            'warning' => ['nullable', 'string'],
        ]);

        $product = $request->user()->skincareProducts()->create($validated);

        return new SkincareProductResource($product->load(['concern', 'skinType', 'doctor.roles']));
    }

    public function show(SkincareProduct $skincareProduct)
    {
        return new SkincareProductResource($skincareProduct->load(['concern', 'skinType', 'doctor.roles']));
    }

    public function adminIndex(Request $request)
    {
        $products = SkincareProduct::with(['concern', 'skinType', 'doctor.roles'])->latest()->paginate($this->perPage($request));

        return SkincareProductResource::collection($products);
    }

    public function update(Request $request, SkincareProduct $skincareProduct)
    {
        abort_unless($skincareProduct->doctor_id === $request->user()->id || $request->user()->hasRole('admin'), 403);

        $this->resolveRelationIdentifiers($request);

        $validated = $request->validate([
            'concern_id' => ['sometimes', 'exists:skin_concerns,id'],
            'skin_type_id' => ['nullable', 'exists:skin_types,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'string', 'max:255'],
            'gender' => ['nullable', Rule::enum(ProductGender::class)],
            'key_ingredients' => ['nullable', 'string'],
            'usage_instruction' => ['sometimes', 'string'],
            'warning' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $skincareProduct->update($validated);

        return new SkincareProductResource($skincareProduct->fresh()->load(['concern', 'skinType', 'doctor.roles']));
    }

    public function destroy(Request $request, SkincareProduct $skincareProduct)
    {
        abort_unless($skincareProduct->doctor_id === $request->user()->id || $request->user()->hasRole('admin'), 403);

        $skincareProduct->delete();

        return $this->successResponse(null, ['message' => 'Produk berhasil dihapus']);
    }

    /**
     * Terima uuid (atau id internal) pada concern_id / skin_type_id lalu
     * ganti di request dengan ID internal sebelum validasi berjalan.
     */
    private function resolveRelationIdentifiers(Request $request): void
    {
        if ($request->filled('concern_id')) {
            $request->merge([
                'concern_id' => UuidResolver::resolve(SkinConcern::class, $request->input('concern_id')),
            ]);
        }

        if ($request->filled('skin_type_id')) {
            $request->merge([
                'skin_type_id' => UuidResolver::resolve(SkinType::class, $request->input('skin_type_id')),
            ]);
        }
    }
}
