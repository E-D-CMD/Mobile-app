<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /** GET /api/admin/categories */
    public function index(): JsonResponse
    {
        $categories = Category::orderBy('name')->get()->map(fn (Category $category) => $this->serialize($category));

        return response()->json([
            'success' => true,
            'data' => ['categories' => $categories],
        ]);
    }

    /** POST /api/admin/categories */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create([
            'name' => $request->string('name')->toString(),
            'slug' => Str::slug($request->string('name')->toString()),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully.',
            'data' => ['category' => $this->serialize($category)],
        ], 201);
    }

    /** PUT /api/admin/categories/{category} */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $oldName = $category->name;
        $newName = $request->string('name')->toString();

        $category->update([
            'name' => $newName,
            'slug' => Str::slug($newName),
        ]);

        // Products store the category as a plain string (see
        // ProductController::serializeProduct in the customer-facing API),
        // so renaming a category has to cascade to every product using the
        // old name or they'd silently fall out of the category.
        if ($oldName !== $newName) {
            Product::where('category', $oldName)->update(['category' => $newName]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully.',
            'data' => ['category' => $this->serialize($category->fresh())],
        ]);
    }

    /** DELETE /api/admin/categories/{category} */
    public function destroy(Category $category): JsonResponse
    {
        $productCount = $category->productCount();

        if ($productCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Can't delete \"{$category->name}\" — {$productCount} product(s) still use it. Reassign or delete those products first.",
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully.',
        ]);
    }

    private function serialize(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'product_count' => $category->productCount(),
        ];
    }
}
