<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'subtotal',
        'shipping_fee',
        'total',
        'payment_method',
        'carrier',
        'tracking_number',
        'shipping_address',
        'processing_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
    ];

    protected $casts = [
        'subtotal'      => 'decimal:2',
        'shipping_fee'  => 'decimal:2',
        'total'         => 'decimal:2',
        'processing_at' => 'datetime',
        'shipped_at'    => 'datetime',
        'delivered_at'  => 'datetime',
        'cancelled_at'  => 'datetime',
    ];

    // Only appended on single-order responses (see OrderController::show),
    // where the tracking timeline is actually needed — kept off the list
    // index to avoid computing it for every row unnecessarily.
    protected $appends = [];

    // ── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    // Ordered list of tracking steps for the Order Tracking screen. Built
    // from the order's own timestamp columns rather than a separate history
    // table, since a single linear status progression is all the schema
    // currently needs; a dedicated order_status_history table would be the
    // next step if branching/re-opened statuses are ever required.
    public function getTrackingTimelineAttribute(): array
    {
        $steps = [
            ['status' => 'pending',    'label' => 'Order placed', 'at' => $this->created_at],
            ['status' => 'processing', 'label' => 'Processing',   'at' => $this->processing_at],
            ['status' => 'shipped',    'label' => 'Shipped',      'at' => $this->shipped_at],
            ['status' => 'delivered',  'label' => 'Delivered',    'at' => $this->delivered_at],
        ];

        if ($this->status === 'cancelled') {
            return [
                ['status' => 'pending',   'label' => 'Order placed', 'at' => $this->created_at, 'completed' => true],
                ['status' => 'cancelled', 'label' => 'Cancelled',    'at' => $this->cancelled_at, 'completed' => true],
            ];
        }

        return array_map(
            fn ($step) => $step + ['completed' => !is_null($step['at'])],
            $steps
        );
    }
}
