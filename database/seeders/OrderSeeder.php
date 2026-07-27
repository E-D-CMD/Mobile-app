<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        // Predictable demo login so the mobile app / QA can test the
        // Profile and Order Tracking screens without registering first.
        //
        // IMPORTANT: do NOT wrap this in Hash::make(). The User model casts
        // 'password' => 'hashed' (see app/Models/User.php), so Eloquent
        // already hashes it automatically on save. Hashing it again here
        // would double-hash the password, and login would never match it.
        $user = User::firstOrCreate(
            ['email' => 'demo@perfumehub.test'],
            [
                'name'     => 'Demo User',
                'password' => 'password',
            ]
        );

        $products = Product::inRandomOrder()->limit(20)->get();

        Order::factory()
            ->count(6)
            ->for($user)
            ->create()
            ->each(function (Order $order) use ($products) {
                $itemCount = fake()->numberBetween(1, 3);

                for ($i = 0; $i < $itemCount; $i++) {
                    if ($products->isNotEmpty()) {
                        OrderItem::factory()
                            ->forProduct($products->random(), fake()->numberBetween(1, 2))
                            ->for($order)
                            ->create();
                    } else {
                        OrderItem::factory()->for($order)->create();
                    }
                }

                // Keep the order total consistent with its actual line items.
                $subtotal = $order->items()->sum('line_total');
                $order->update([
                    'subtotal' => $subtotal,
                    'total'    => $subtotal + $order->shipping_fee,
                ]);
            });
    }
}
