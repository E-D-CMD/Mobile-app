<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /** GET /api/admin/dashboard */
    public function index(): JsonResponse
    {
        $completedStatuses = ['delivered'];

        $monthlySales = Order::whereIn('status', $completedStatuses)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('total');

        $totalRevenue = Order::whereIn('status', $completedStatuses)->sum('total');

        return response()->json([
            'success' => true,
            'data' => [
                'total_products' => Product::count(),
                'products_in_stock' => Product::where('stock', '>', 0)->count(),
                'out_of_stock' => Product::where('stock', '<=', 0)->count(),
                'low_stock' => Product::whereBetween('stock', [1, 10])->count(),
                'total_categories' => Category::count(),
                'total_orders' => Order::count(),
                'pending_orders' => Order::where('status', 'pending')->count(),
                'completed_orders' => Order::whereIn('status', $completedStatuses)->count(),
                'total_users' => User::where('role', 'customer')->count(),
                'total_revenue' => round((float) $totalRevenue, 2),
                'todays_orders' => Order::whereDate('created_at', now()->toDateString())->count(),
                'monthly_sales' => round((float) $monthlySales, 2),
                'recent_orders' => Order::with('user')->latest()->take(5)->get()->map(fn (Order $order) => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total' => $order->total,
                    'customer_name' => $order->user?->name,
                    'created_at' => $order->created_at,
                ])->values(),
            ],
        ]);
    }
}
