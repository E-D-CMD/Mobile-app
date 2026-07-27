<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_is_available(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_products_response_matches_the_frontend_contract(): void
    {
        Product::create([
            'name' => 'Test Elixir',
            'brand' => 'PerfumeHub',
            'description' => 'Integration test perfume.',
            'category' => 'Women',
            'gender' => 'female',
            'price' => 99.99,
            'stock' => 7,
            'size' => '100ml',
            'images' => ['https://example.test/perfume.png'],
            'rating' => 4.5,
            'review_count' => 10,
            'is_featured' => true,
            'is_active' => true,
        ]);

        $this->getJson('/api/products?per_page=10')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.products.0.name', 'Test Elixir')
            ->assertJsonPath('data.products.0.stock_quantity', 7)
            ->assertJsonPath('data.products.0.category.name', 'Women')
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_authenticated_order_detail_matches_the_tracking_screen_contract(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'ORD-TEST-001',
            'status' => 'processing',
            'subtotal' => 99.99,
            'shipping_fee' => 0,
            'total' => 99.99,
            'payment_method' => 'cash_on_delivery',
            'shipping_address' => 'Lusaka, Zambia',
            'processing_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Test Elixir',
            'unit_price' => 99.99,
            'quantity' => 1,
            'line_total' => 99.99,
        ]);

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order.order_number', 'ORD-TEST-001')
            ->assertJsonPath('data.order.total_amount', '99.99')
            ->assertJsonPath('data.order.item_count', 1)
            ->assertJsonPath('data.order.items.0.product_name', 'Test Elixir')
            ->assertJsonStructure([
                'data' => [
                    'order' => [
                        'tracking_timeline',
                    ],
                ],
            ]);
    }

    public function test_order_endpoints_require_a_bearer_token(): void
    {
        $this->getJson('/api/orders')->assertUnauthorized();
    }
}
