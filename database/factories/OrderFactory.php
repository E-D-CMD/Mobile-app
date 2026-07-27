<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $status = fake()->randomElement([
            'pending', 'processing', 'shipped', 'delivered', 'cancelled',
        ]);

        $createdAt = fake()->dateTimeBetween('-2 months', '-1 days');

        $subtotal    = fake()->randomFloat(2, 30, 400);
        $shippingFee = fake()->randomElement([0, 4.99, 9.99]);

        return [
            'user_id'          => User::factory(),
            'order_number'     => 'PH-' . strtoupper(Str::random(8)),
            'status'           => $status,
            'subtotal'         => $subtotal,
            'shipping_fee'     => $shippingFee,
            'total'            => $subtotal + $shippingFee,
            'carrier'          => in_array($status, ['shipped', 'delivered']) ? fake()->randomElement(['DHL', 'FedEx', 'UPS']) : null,
            'tracking_number'  => in_array($status, ['shipped', 'delivered']) ? strtoupper(Str::random(12)) : null,
            'shipping_address' => fake()->streetAddress() . ', ' . fake()->city() . ', ' . fake()->postcode(),
            'processing_at'    => in_array($status, ['processing', 'shipped', 'delivered']) ? fake()->dateTimeBetween($createdAt, '+1 day') : null,
            'shipped_at'       => in_array($status, ['shipped', 'delivered']) ? fake()->dateTimeBetween($createdAt, '+3 days') : null,
            'delivered_at'     => $status === 'delivered' ? fake()->dateTimeBetween($createdAt, '+7 days') : null,
            'cancelled_at'     => $status === 'cancelled' ? fake()->dateTimeBetween($createdAt, '+1 day') : null,
            'created_at'       => $createdAt,
            'updated_at'       => $createdAt,
        ];
    }
}
