<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Store;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return view('admin.dashboard', [
            'storeCount' => Store::count(),
            'pendingStores' => Store::where('is_approved', false)->count(),
            'orderCount' => Order::count(),
            'customerCount' => Customer::count(),
            'totalSales' => Order::whereIn('status', OrderStatus::settled())->sum('amount'),
            'recentOrders' => Order::with(['store', 'customer'])->latest('id')->limit(10)->get(),
        ]);
    }
}
