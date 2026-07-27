<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /** GET /api/orders */
    public function index(Request $request): JsonResponse
    {
        $query = Order::forUser($request->user()->id)
            ->with('items')
            ->withCount('items')
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $perPage = max(1, min((int) $request->input('per_page', 10), 50));
        $orders = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => collect($orders->items())
                    ->map(fn (Order $order) => $this->serializeOrder($order, false))
                    ->values(),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                ],
            ],
        ]);
    }

    /**
     * POST /api/orders
     *
     * Creates an order from the mobile checkout screen's cart + shipping
     * form. Replaces the frontend's previous mock order response with a
     * real, persisted order and a matching stock deduction.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.product_id'    => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'      => ['required', 'integer', 'min:1'],
            'shipping_name'         => ['required', 'string', 'max:255'],
            'shipping_address'      => ['required', 'string', 'max:255'],
            'shipping_city'         => ['required', 'string', 'max:255'],
            'shipping_phone'        => ['required', 'string', 'max:255'],
            'payment_method'        => ['nullable', 'string', 'max:255'],
        ]);

        $productIds = collect($data['items'])->pluck('product_id')->unique();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        // Validate stock availability before writing anything. Returned as
        // a plain 422 (rather than thrown as a ValidationException) so the
        // specific reason lands in `message`, which is what the frontend's
        // ApiRequestError surfaces to the checkout screen.
        foreach ($data['items'] as $line) {
            $product = $products->get($line['product_id']);

            if (!$product || !$product->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'One of the items in your cart is no longer available.',
                ], 422);
            }

            if ($product->stock < $line['quantity']) {
                return response()->json([
                    'success' => false,
                    'message' => "Not enough stock for \"{$product->name}\" (only {$product->stock} left).",
                ], 422);
            }
        }

        $order = DB::transaction(function () use ($request, $data, $products) {
            $subtotal = 0;
            $lineItems = [];

            foreach ($data['items'] as $line) {
                $product = $products->get($line['product_id']);
                $unitPrice = $product->effective_price;
                $lineTotal = round($unitPrice * $line['quantity'], 2);
                $subtotal += $lineTotal;

                $lineItems[] = [
                    'product_id'    => $product->id,
                    'product_name'  => $product->name,
                    'product_image' => $product->images[0] ?? null,
                    'unit_price'    => $unitPrice,
                    'quantity'      => $line['quantity'],
                    'line_total'    => $lineTotal,
                ];

                // Deduct stock now that we've confirmed availability above.
                $product->decrement('stock', $line['quantity']);
            }

            $shippingFee = 0;
            $shippingAddress = trim("{$data['shipping_name']}, {$data['shipping_address']}, {$data['shipping_city']} — {$data['shipping_phone']}");

            $order = Order::create([
                'user_id'         => $request->user()->id,
                'order_number'    => $this->generateOrderNumber(),
                'status'          => 'pending',
                'subtotal'        => $subtotal,
                'shipping_fee'    => $shippingFee,
                'total'           => $subtotal + $shippingFee,
                'payment_method'  => $data['payment_method'] ?? 'cash_on_delivery',
                'shipping_address'=> $shippingAddress,
            ]);

            foreach ($lineItems as $item) {
                $order->items()->create($item);
            }

            return $order;
        });

        $order->load('items');
        $order->loadCount('items');

        return response()->json([
            'success' => true,
            'data' => [
                'order' => $this->serializeOrder($order, true),
            ],
        ], 201);
    }

    private function generateOrderNumber(): string
    {
        do {
            $candidate = 'ORD-' . now()->format('ymd') . '-' . strtoupper(Str::random(5));
        } while (Order::where('order_number', $candidate)->exists());

        return $candidate;
    }

    /** GET /api/orders/{order} */
    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        $order->load('items');
        $order->loadCount('items');

        return response()->json([
            'success' => true,
            'data' => [
                'order' => $this->serializeOrder($order, true),
            ],
        ]);
    }

    private function serializeOrder(Order $order, bool $includeTimeline): array
    {
        $payload = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'subtotal' => $order->subtotal,
            'shipping_fee' => $order->shipping_fee,
            'total' => $order->total,
            'total_amount' => $order->total,
            'payment_method' => $order->payment_method ?? 'cash_on_delivery',
            'carrier' => $order->carrier,
            'tracking_number' => $order->tracking_number,
            'shipping_address' => $order->shipping_address,
            'item_count' => (int) ($order->items_count ?? $order->items->count()),
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
            'items' => $order->items
                ->map(fn (OrderItem $item) => $this->serializeItem($item))
                ->values(),
        ];

        if ($includeTimeline) {
            $payload['tracking_timeline'] = $order->tracking_timeline;
        }

        return $payload;
    }

    private function serializeItem(OrderItem $item): array
    {
        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product_name' => $item->product_name,
            'product_image' => $item->product_image,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'price' => $item->unit_price,
            'line_total' => $item->line_total,
            'subtotal' => $item->line_total,
        ];
    }
}
