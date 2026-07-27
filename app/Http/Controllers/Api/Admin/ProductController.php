<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Requests\Admin\UpdateStockRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * GET /api/admin/products
     * Search, filter by category/status, sort, paginate — same query-param
     * shape as the customer-facing ProductController::index, plus admin-only
     * filters (status, low stock) since this list is never public.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query();

        if ($request->filled('search')) {
            $term = $request->string('search')->toString();
            $query->where(function ($builder) use ($term) {
                $builder->where('name', 'like', "%{$term}%")
                    ->orWhere('brand', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }

        if ($request->filled('status')) {
            match ($request->string('status')->toString()) {
                'active' => $query->where('is_active', true),
                'inactive' => $query->where('is_active', false),
                'out_of_stock' => $query->where('stock', '<=', 0),
                'low_stock' => $query->whereBetween('stock', [1, 10]),
                default => null,
            };
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['price', 'stock', 'name', 'created_at'];

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortDirection);
        }

        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'products' => collect($products->items())
                    ->map(fn (Product $product) => $this->serialize($product))
                    ->values(),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
            ],
        ]);
    }

    /** GET /api/admin/products/{product} */
    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['product' => $this->serialize($product)],
        ]);
    }

    /** POST /api/admin/products */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $product = Product::create([
            ...collect($validated)->except(['images'])->toArray(),
            'images' => $this->storeImages($request),
            'is_featured' => $request->boolean('is_featured'),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'data' => ['product' => $this->serialize($product)],
        ], 201);
    }

    /** PUT/POST /api/admin/products/{product} */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $validated = $request->validated();

        $newImagePaths = $this->storeImages($request);

        if ($newImagePaths || $request->has('existing_images')) {
            // Images the admin did NOT keep get deleted from disk so
            // storage/app/public/products doesn't accumulate orphans.
            $kept = $request->input('existing_images', []);
            $current = is_array($product->images) ? $product->images : [];
            $removed = array_diff($current, $kept);

            foreach ($removed as $url) {
                $this->deleteStoredImage($url);
            }

            $validated['images'] = array_values(array_merge($kept, $newImagePaths));
        }

        $product->update(collect($validated)->except(['existing_images'])->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'data' => ['product' => $this->serialize($product->fresh())],
        ]);
    }

    /** DELETE /api/admin/products/{product} */
    public function destroy(Product $product): JsonResponse
    {
        foreach ((is_array($product->images) ? $product->images : []) as $url) {
            $this->deleteStoredImage($url);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.',
        ]);
    }

    /** PUT /api/admin/products/{product}/status — enable/disable */
    public function toggleStatus(Request $request, Product $product): JsonResponse
    {
        $request->validate(['is_active' => ['required', 'boolean']]);

        $product->update(['is_active' => $request->boolean('is_active')]);

        return response()->json([
            'success' => true,
            'message' => $product->is_active ? 'Product enabled.' : 'Product disabled.',
            'data' => ['product' => $this->serialize($product->fresh())],
        ]);
    }

    /** PUT /api/admin/products/{product}/stock */
    public function updateStock(UpdateStockRequest $request, Product $product): JsonResponse
    {
        $quantity = (int) $request->input('quantity');

        $newStock = match ($request->input('action')) {
            'set' => $quantity,
            'increase' => $product->stock + $quantity,
            'decrease' => max(0, $product->stock - $quantity),
        };

        $product->update(['stock' => $newStock]);

        return response()->json([
            'success' => true,
            'message' => 'Stock updated successfully.',
            'data' => ['product' => $this->serialize($product->fresh())],
        ]);
    }

    /**
     * Stores any uploaded files under storage/app/public/products and
     * returns their public URLs. Requires `php artisan storage:link` to
     * have been run (see README) so storage/app/public is served at
     * /storage/*.
     */
    private function storeImages(Request $request): array
    {
        if (! $request->hasFile('images')) {
            return [];
        }

        return collect($request->file('images'))
            ->map(function ($file) {
                $path = $file->store('products', 'public');

                return asset('storage/' . $path);
            })
            ->values()
            ->toArray();
    }

    private function deleteStoredImage(string $url): void
    {
        // Stored URLs look like {APP_URL}/storage/products/xxxx.jpg —
        // strip everything up to and including '/storage/' to recover the
        // disk-relative path Storage::disk('public') expects.
        $marker = '/storage/';
        $position = strpos($url, $marker);

        if ($position === false) {
            return; // not a locally-stored file (e.g. a seeded external URL)
        }

        $path = substr($url, $position + strlen($marker));

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function serialize(Product $product): array
    {
        $images = is_array($product->images) ? $product->images : [];

        return [
            'id' => $product->id,
            'name' => $product->name,
            'brand' => $product->brand,
            'sku' => $product->sku,
            'description' => $product->description,
            'category' => $product->category,
            'gender' => $product->gender,
            'price' => $product->price,
            'discount_price' => $product->discount_price,
            'stock' => $product->stock,
            'stock_status' => $product->stock_status,
            'size' => $product->size,
            'image_url' => $images[0] ?? null,
            'images' => $images,
            'is_featured' => $product->is_featured,
            'is_active' => $product->is_active,
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
        ];
    }
}
