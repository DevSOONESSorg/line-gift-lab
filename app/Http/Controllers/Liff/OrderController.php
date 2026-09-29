<?php

namespace App\Http\Controllers\Liff;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use App\Services\Notifier;
use App\Services\OrderService;
use App\Support\Inside;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function store(Request $r, string $slug)
    {
        $store = Store::where(['slug' => $slug, 'is_approved' => true])->firstOrFail();
        $customer = $r->attributes->get('customer');
        if (! $customer) return back()->withErrors(['order' => 'LINEのユーザー情報が取れませんでした。LINEの中で開き直してください。']);

        $data = $r->validate([
            'menu_id' => 'required|integer', 'message' => 'nullable|max:300', 'payment_method' => 'required|in:card,bank_transfer',
        ], [], ['menu_id' => '商品', 'payment_method' => '支払い方法']);
        $menu = $store->menus()->where('is_active', true)->find($data['menu_id']);
        if (! $menu) return back()->withInput()->withErrors(['menu_id' => '商品を選んでください。']);

        // 身分証（必須のお店だけ。一度登録すれば他のお店でも有効）
        if ($store->require_id_verification && ! $customer->id_verified_at) {
            if (! $r->hasFile('id_image')) return back()->withInput()->withErrors(['id_image' => 'このお店は身分証の画像が必要です。']);
            $r->file('id_image')->store("id-documents/{$customer->id}");   // 公開しない場所（storage/app/private）に保存
            $customer->update(['id_verified_at' => now()]);
            Inside::ok('app', "{$customer->line_display_name} さんの身分証を登録しました（次からは、どのお店でも不要）");
        }

        $method = PaymentMethod::from($data['payment_method']);
        $last4 = null;
        if ($method === PaymentMethod::Card) {
            if ($customer->hasCard() && $r->input('use_saved') !== '0') {
                $last4 = $customer->card_last4;
                Inside::info('app', "登録済みのカード **** {$last4} で支払います（カード番号の入力なし）");
            } else {
                $card = preg_replace('/\s+/', '', (string) $r->input('card_number'));
                $result = config("lab.test_cards.$card");
                if (! $result) return back()->withInput()->withErrors(['card_number' => 'カード番号が正しくありません（体験ではテストカード 4242 4242 4242 4242）。']);
                if (! preg_match('#^(0[1-9]|1[0-2])/(\d{2})$#', (string) $r->input('exp'), $m) || (2000 + (int) $m[2]) * 12 + (int) $m[1] < now()->year * 12 + now()->month) {
                    return back()->withInput()->withErrors(['exp' => '有効期限は「月/年」（例 12/40）で、今日以降にしてください。']);
                }
                if (! preg_match('/^\d{3}$/', (string) $r->input('cvc'))) return back()->withInput()->withErrors(['cvc' => 'CVC は3桁の数字です。']);
                if ($result === 'declined') {
                    Inside::ng('app', 'カード登録 → 拒否されました（テストカード 4000 0000 0000 0002）');
                    return back()->withInput()->withErrors(['card_number' => 'カードが拒否されました。別のカードをお試しください。']);
                }
                $last4 = substr($card, -4);
                $customer->update(['card_last4' => $last4, 'card_exp' => $r->input('exp')]);
                Inside::ok('app', "カード **** {$last4} を登録しました（次回からは入力不要）", '自社が保存したのは「下4桁と有効期限」だけ。本物は、カード番号そのものは決済会社が保管し、自社はそのカードを指す「ID」を持ちます');
            }
        }

        $route = $r->session()->get("route.{$store->id}", 'original');
        $order = OrderService::create($store, $menu, $customer, $method, $route, $data['message'] ?? null, $last4);

        if ($method === PaymentMethod::Card) {
            Notifier::toCustomer($order, "{$store->name}「{$menu->name}」のギフトを贈りました（注文番号 #{$order->id}）。\nお店が受け取るとお礼が届きます。");
            Notifier::toOwner($store, "【{$store->name}】ギフトが届きました（#{$order->id} {$menu->name}）。\nメニューの「店舗管理」→ ギフト一覧 から受け取ってください。");
        } else {
            Notifier::toCustomer($order, "{$store->name}「{$menu->name}」の注文を受け付けました（#{$order->id}）。\n{$order->expires_at->format('m/d H:i')} までに ".number_format($order->amount).'円 をお振込みください。');
        }
        return redirect()->route('liff.order.done', $order);
    }

    public function done(Request $r, Order $order)
    {
        abort_unless($order->customer_id === $r->attributes->get('customer')?->id, 403);
        return view('liff.done', ['order' => $order->load('store'), 'openedLiff' => $r->session()->get('opened_liff')]);
    }
}
