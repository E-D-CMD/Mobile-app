<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    // Products reference categories by name (products.category is a plain
    // string column, matching the existing customer-facing API contract —
    // see ProductController::serializeProduct). This counts how many
    // products currently use this category's name, so the admin UI can
    // block deleting a category that's still assigned to products.
    public function productCount(): int
    {
        return Product::where('category', $this->name)->count();
    }
}
