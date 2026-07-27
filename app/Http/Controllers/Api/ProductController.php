<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * GET /api/products
     *
     * Supports search, filtering, sorting, and pagination. The response is
     * intentionally shaped for the Expo frontend so it does not need to know
     * about Laravel model implementation details such as `stock` or `images`.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::active();

        if ($request->filled('search')) {
            $term = $request->string('search')->toString();
            $query->where(function ($builder) use ($term) {
                $builder->where('name', 'like', "%{$term}%")
                    ->orWhere('brand', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }

        if ($request->filled('gender')) {
            $query->where('gender', $request->string('gender')->toString());
        }

        if ($request->filled('brand')) {
            $query->where('brand', $request->string('brand')->toString());
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->input('max_price'));
        }

        if ($request->boolean('in_stock')) {
            $query->inStock();
        }

        if ($request->boolean('featured')) {
            $query->featured();
        }

        if ($request->boolean('on_sale')) {
            $query->whereNotNull('discount_price');
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['price', 'rating', 'name', 'created_at', 'review_count'];

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortDirection);
        }

        $perPage = max(1, min((int) $request->input('per_page', 12), 50));
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'products' => collect($products->items())
                    ->map(fn (Product $product) => $this->serializeProduct($product))
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

    /** GET /api/products/{product} */
    public function show(Product $product): JsonResponse
    {
        if (! $product->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'product' => $this->serializeProduct($product),
            ],
        ]);
    }

    /** GET /api/products/filters/options */
    public function filterOptions(): JsonResponse
    {
        $categories = Product::active()
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->map(fn (string $category) => [
                'id' => Str::slug($category),
                'name' => $category,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories,
                'brands' => Product::active()->distinct()->orderBy('brand')->pluck('brand')->values(),
                'genders' => ['male', 'female', 'unisex'],
                'price_range' => [
                    'min' => Product::active()->min('price'),
                    'max' => Product::active()->max('price'),
                ],
            ],
        ]);
    }

    private function serializeProduct(Product $product): array
    {
        $images = is_array($product->images) ? $product->images : [];
        $categoryName = $product->category;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'brand' => $product->brand,
            'description' => $product->description,
            'price' => $product->price,
            'discount_price' => $product->discount_price,
            'effective_price' => number_format((float) $product->effective_price, 2, '.', ''),
            'stock_quantity' => $product->stock,
            'image_url' => $images[0] ?? null,
            'images' => $images,
            'category' => [
                'id' => Str::slug($categoryName),
                'name' => $categoryName,
            ],
            'gender' => $product->gender,
            'size' => $product->size,
            'notes' => $product->notes,
            'rating' => $product->rating,
            'review_count' => $product->review_count,
            'is_featured' => $product->is_featured,
            'is_on_sale' => $product->is_on_sale,
        ];
    }
}
