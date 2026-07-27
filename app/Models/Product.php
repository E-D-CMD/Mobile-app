<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'brand',
        'sku',
        'description',
        'category',
        'gender',
        'price',
        'discount_price',
        'stock',
        'size',
        'notes',
        'images',
        'rating',
        'review_count',
        'is_featured',
        'is_active',
    ];

    protected $casts = [
        'notes'          => 'array',
        'images'         => 'array',
        'price'          => 'decimal:2',
        'discount_price' => 'decimal:2',
        'rating'         => 'decimal:2',
        'is_featured'    => 'boolean',
        'is_active'      => 'boolean',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getEffectivePriceAttribute(): float
    {
        return $this->discount_price ?? $this->price;
    }

    public function getIsOnSaleAttribute(): bool
    {
        return !is_null($this->discount_price) && $this->discount_price < $this->price;
    }

    // 'in_stock' (>10), 'low_stock' (1-10), 'out_of_stock' (0). Used by the
    // admin product list/dashboard rather than the customer-facing API,
    // which only needs the raw stock_quantity number.
    public function getStockStatusAttribute(): string
    {
        if ($this->stock <= 0) {
            return 'out_of_stock';
        }

        return $this->stock <= 10 ? 'low_stock' : 'in_stock';
    }
}
