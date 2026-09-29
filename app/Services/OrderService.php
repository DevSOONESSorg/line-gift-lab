<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Store;
use App\Support\Inside;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// =====================================================
// 注文の「作る」「状態を変える」はすべてここを通す
// （画面ごとにバラバラに status を書きかえると、ありえない状態が生まれるため）
// =====================================================
class OrderService
{
    public static function create(Store $store, Menu $menu, Customer $customer, PaymentMethod $method, string $route, ?string $message, ?string $cardLast4): Order
    {
        $rate = CommissionService::rateFor($store);
        $status = $method === PaymentMethod::Card ? OrderStatus::Requested : OrderStatus::AwaitingPayment;
        $expires = $method === PaymentMethod::Card
            ? now()->addDays(config('lab.card_authorization_days'))
            : now()->addDays(config('lab.bank_transfer_days'));

        $order = DB::transaction(function () use ($store, $menu, $customer, $method, $route, $message, $cardLast4, $rate, $status, $expires) {
            $order = Order::create([
                'store_id' => $store->id, 'menu_id' => $menu->id, 'customer_id' => $customer->id,
                'menu_name' => $menu->name, 'amount' => $menu->price,
                'commission_rate' => $rate, 'commission' => CommissionService::commission($menu->price, $rate),
                'payment_method' => $method, 'status' => $status, 'route' => $route,
                'message' => $message, 'card_last4' => $cardLast4, 'expires_at' => $expires,
            ]);
            $order->statusLogs()->create(['from' => null, 'to' => $status->value, 'by' => 'customer', 'note' => '注文']);
            return $order;
        });

        Inside::ok('app', "注文 #{$order->id} を作りました → 「{$status->label()}」",
            "店舗: {$store->name}\n商品: {$menu->name} ".number_format($menu->price)."円\n手数料率: {$rate}%（".(CommissionService::rateFor($store) == $store->commission_rate ? 'デフォルト' : '月別').'）= '.number_format($order->commission)."円\n"
            .($method === PaymentMethod::Card
                ? "カード **** {$cardLast4} は「仮押さえ（与信）」だけ。{$expires->format('m/d H:i')} までに「受け取る」されないと期限切れになります"
                : "銀行振込。{$expires->format('m/d H:i')} までに入金が確認できないとキャンセルになります"));
        return $order;
    }

    // 状態を変える。移ってよい先でなければ例外
    public static function transition(Order $order, OrderStatus $to, string $by, string $note = '', array $extra = []): Order
    {
        $from = $order->status;
        if (! $from->canTransitionTo($to)) {
            Inside::ng('app', "注文 #{$order->id}: 「{$from->label()}」から「{$to->label()}」には変えられません", '許される移り変わりは App\\Enums\\OrderStatus::next() に書いてあります');
            throw new RuntimeException("「{$from->label()}」の注文を「{$to->label()}」にはできません。");
        }
        DB::transaction(function () use ($order, $from, $to, $by, $note, $extra) {
            $order->update(array_merge(['status' => $to], $extra));
            $order->statusLogs()->create(['from' => $from->value, 'to' => $to->value, 'by' => $by, 'note' => $note]);
        });
        Inside::ok($by === 'admin' ? 'admin' : 'app', "注文 #{$order->id}: {$from->label()} → {$to->label()}", $note ?: null);
        return $order;
    }

    // 銀行振込の入金を確認した（管理画面）
    public static function confirmPayment(Order $order): Order
    {
        return self::transition($order, OrderStatus::Requested, 'admin', '入金を確認', [
            'paid_at' => now(), 'expires_at' => now()->addDays(config('lab.card_authorization_days')),
        ]);
    }

    // お店のオーナーが「受け取る」→ ここで決済が確定
    public static function receive(Order $order): Order
    {
        $captured = $order->payment_method === PaymentMethod::Card ? '仮押さえしていたカードの決済を確定' : '入金済みのお金を売上として確定';
        return self::transition($order, OrderStatus::Received, 'owner', $captured, [
            'received_at' => now(), 'paid_at' => $order->paid_at ?? now(), 'expires_at' => null,
        ]);
    }

    // お礼（動画・メッセージ）を送った
    public static function thank(Order $order, ?string $videoPath, ?string $message): Order
    {
        return self::transition($order, OrderStatus::Thanked, 'owner', 'お礼を送信', [
            'thanked_at' => now(), 'thank_video_path' => $videoPath, 'thank_message' => $message,
        ]);
    }

    public static function startRefund(Order $order, string $note): Order
    {
        return self::transition($order, OrderStatus::RefundPending, 'admin', $note ?: '返金を受け付け');
    }

    public static function completeRefund(Order $order): Order
    {
        return self::transition($order, OrderStatus::Refunded, 'admin', '返金が完了', ['refunded_at' => now()]);
    }

    // 期限が過ぎた注文をまとめて処理（php artisan orders:expire / スケジューラーから）
    public static function expireOverdue(): array
    {
        $done = ['expired' => 0, 'cancelled' => 0];
        $overdue = Order::whereIn('status', [OrderStatus::Requested->value, OrderStatus::AwaitingPayment->value])
            ->whereNotNull('expires_at')->where('expires_at', '<', now())->get();
        foreach ($overdue as $order) {
            if ($order->status === OrderStatus::Requested) {
                self::transition($order, OrderStatus::Expired, 'system', '与信の期限切れ（仮押さえを解放。お金は動いていない）');
                $done['expired']++;
            } else {
                self::transition($order, OrderStatus::Cancelled, 'system', '入金期限切れ', ['cancelled_at' => now()]);
                $done['cancelled']++;
            }
        }
        return $done;
    }
}
