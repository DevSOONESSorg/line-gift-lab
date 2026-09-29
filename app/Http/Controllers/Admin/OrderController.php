<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Notifier;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class OrderController extends Controller
{
    public function index(Request $r)
    {
        $orders = Order::with(['store', 'customer'])
            ->when($r->status, fn ($q) => $q->where('status', $r->status))
            ->when($r->payment_method, fn ($q) => $q->where('payment_method', $r->payment_method))
            ->when($r->start_date, fn ($q) => $q->whereDate('created_at', '>=', $r->start_date))
            ->when($r->end_date, fn ($q) => $q->whereDate('created_at', '<=', $r->end_date))
            ->latest('id')->paginate(20)->withQueryString();
        return view('admin.orders.index', ['orders' => $orders, 'statuses' => OrderStatus::cases(), 'methods' => PaymentMethod::cases()]);
    }

    public function show(Order $order)
    {
        $order->load(['store', 'customer', 'statusLogs']);
        $videoUrl = $order->thank_video_path ? URL::temporarySignedRoute('media.thank-video', now()->addMinutes(10), $order) : null;
        return view('admin.orders.show', compact('order', 'videoUrl'));
    }

    // 以下の3つは、教材で「状態の移り変わり」を体験するためのボタン。
    // 実物では、入金確認や返金は決済会社・銀行の処理と連動して行われます。
    public function confirmPayment(Order $order)
    {
        return $this->run($order, function () use ($order) {
            OrderService::confirmPayment($order);
            Notifier::toCustomer($order, "{$order->store->name}「{$order->menu_name}」のご入金を確認しました。お店で受け取られると完了です。");
            Notifier::toOwner($order->store, "【{$order->store->name}】ギフトが届きました（#{$order->id} {$order->menu_name}）。店舗管理から受け取ってください。");
        }, '入金を確認しました');
    }

    public function refund(Request $r, Order $order)
    {
        return $this->run($order, fn () => OrderService::startRefund($order, (string) $r->input('note')), '返金処理待ちにしました');
    }

    public function refundComplete(Order $order)
    {
        return $this->run($order, fn () => OrderService::completeRefund($order), '返金済みにしました');
    }

    private function run(Order $order, callable $fn, string $ok)
    {
        try { $fn(); } catch (\RuntimeException $e) { return back()->with('msg', $e->getMessage()); }
        return redirect()->route('admin.orders.show', $order)->with('msg', $ok);
    }
}
