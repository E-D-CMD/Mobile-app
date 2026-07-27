<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /** GET /api/admin/orders */
    public function index(Request $request): JsonResponse
    {
        $query = Order::with('user')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $term = $request->string('search')->toString();
            $query->where(function ($builder) use ($term) {
                $builder->where('order_number', 'like', "%{$term}%")
                    ->orWhereHas('user', function ($userQuery) use ($term) {
                        $userQuery->where('name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%");
                    });
            });
        }

        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $orders = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => collect($orders->items())->map(fn (Order $order) => $this->serialize($order, detailed: false))->values(),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                ],
            ],
        ]);
    }

    /** GET /api/admin/orders/{order} */
    public function show(Order $order): JsonResponse
    {
        $order->load(['user', 'items']);

        return response()->json([
            'success' => true,
            'data' => ['order' => $this->serialize($order, detailed: true)],
        ]);
    }

    /**
     * PUT /api/admin/orders/{order}
     * Updates status and stamps the matching timestamp column, which is
     * what Order::getTrackingTimelineAttribute() reads from for the
     * customer-facing tracking screen — so an admin status change is
     * immediately reflected in the customer's order tracking too.
     */
    public function update(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $status = $request->string('status')->toString();

        $timestampColumn = match ($status) {
            'processing' => 'processing_at',
            'shipped' => 'shipped_at',
            'delivered' => 'delivered_at',
            'cancelled' => 'cancelled_at',
            default => null,
        };

        $order->update([
            'status' => $status,
            'carrier' => $request->input('carrier', $order->carrier),
            'tracking_number' => $request->input('tracking_number', $order->tracking_number),
            ...($timestampColumn && ! $order->{$timestampColumn} ? [$timestampColumn => now()] : []),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully.',
            'data' => ['order' => $this->serialize($order->fresh(['user', 'items']), detailed: true)],
        ]);
    }

    private function serialize(Order $order, bool $detailed): array
    {
        $base = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'customer' => [
                'id' => $order->user?->id,
                'name' => $order->user?->name,
                'email' => $order->user?->email,
            ],
            'subtotal' => $order->subtotal,
            'shipping_fee' => $order->shipping_fee,
            'total' => $order->total,
            'payment_method' => $order->payment_method,
            'item_count' => $order->items_count ?? $order->items()->count(),
            'created_at' => $order->created_at,
        ];

        if (! $detailed) {
            return $base;
        }

        return [
            ...$base,
            'carrier' => $order->carrier,
            'tracking_number' => $order->tracking_number,
            'shipping_address' => $order->shipping_address,
            'tracking_timeline' => $order->tracking_timeline,
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'product_image' => $item->product_image,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
            ])->values(),
        ];
    }
}
