<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 3);
        $unitPrice = fake()->randomFloat(2, 30, 250);

        return [
            'order_id'      => Order::factory(),
            'product_id'    => null,
            'product_name'  => fake()->words(3, true),
            'product_image' => 'https://placehold.co/400x400?text=Perfume',
            'unit_price'    => $unitPrice,
            'quantity'      => $quantity,
            'line_total'    => $unitPrice * $quantity,
        ];
    }

    // Snapshot a real seeded Product instead of fake() values, when one is
    // available — keeps demo data internally consistent.
    public function forProduct(Product $product, int $quantity = 1): static
    {
        return $this->state(fn () => [
            'product_id'    => $product->id,
            'product_name'  => $product->name,
            'product_image' => $product->images[0] ?? null,
            'unit_price'    => $product->effective_price,
            'quantity'      => $quantity,
            'line_total'    => $product->effective_price * $quantity,
        ]);
    }
}
