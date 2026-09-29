<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

// ユーザー管理（ギフトを贈るお客さん＝LINEユーザー）
class CustomerController extends Controller
{
    public function index(Request $r)
    {
        $customers = Customer::withCount('orders')
            ->when($r->q, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$r->q}%")->orWhere('line_display_name', 'like', "%{$r->q}%")->orWhere('email', 'like', "%{$r->q}%")))
            ->latest('id')->paginate(20)->withQueryString();
        return view('admin.users.index', compact('customers'));
    }

    public function show(Customer $customer)
    {
        return view('admin.users.show', ['customer' => $customer, 'orders' => $customer->orders()->with('store')->latest('id')->get()]);
    }
}
