<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /** GET /api/admin/users */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->where('role', 'customer');

        if ($request->filled('search')) {
            $term = $request->string('search')->toString();
            $query->where(function ($builder) use ($term) {
                $builder->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }

        $perPage = max(1, min((int) $request->input('per_page', 20), 100));
        $users = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'users' => collect($users->items())->map(fn (User $user) => $this->serialize($user))->values(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ],
            ],
        ]);
    }

    /** GET /api/admin/users/{user} — profile + order history */
    public function show(User $user): JsonResponse
    {
        $orders = Order::where('user_id', $user->id)->latest()->get();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->serialize($user),
                'orders' => $orders->map(fn (Order $order) => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total' => $order->total,
                    'created_at' => $order->created_at,
                ])->values(),
            ],
        ]);
    }

    /** PUT /api/admin/users/{user}/disable */
    public function disable(User $user): JsonResponse
    {
        $user->update(['is_suspended' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Account disabled successfully.',
            'data' => ['user' => $this->serialize($user->fresh())],
        ]);
    }

    /** PUT /api/admin/users/{user}/enable */
    public function enable(User $user): JsonResponse
    {
        $user->update(['is_suspended' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Account enabled successfully.',
            'data' => ['user' => $this->serialize($user->fresh())],
        ]);
    }

    private function serialize(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_suspended' => (bool) $user->is_suspended,
            'created_at' => $user->created_at,
        ];
    }
}
