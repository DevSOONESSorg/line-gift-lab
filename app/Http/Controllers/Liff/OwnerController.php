<?php

namespace App\Http\Controllers\Liff;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuTemplate;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Store;
use App\Services\Notifier;
use App\Services\OrderService;
use App\Support\Inside;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

// =====================================================
// 店舗オーナーの画面（運営の公式LINEのリッチメニューから開く）
//   出店登録 → 商品登録 → ギフト一覧（受け取る・お礼を送る）
// 「このお店のオーナーか」は、出店登録したときの LINEユーザーID で判断します
// =====================================================
class OwnerController extends Controller
{
    private function customer(Request $r)
    {
        $c = $r->attributes->get('customer');
        abort_unless($c, 403, 'この画面は「おくりギフト(dev)」のリッチメニューから開きます。');
        return $c;
    }

    private function own(Request $r, Store $store): void
    {
        abort_unless($store->owner_line_user_id === $this->customer($r)->line_user_id, 403, 'このお店のオーナーではありません。');
    }

    public function register(Request $r)
    {
        $c = $this->customer($r);
        return view('liff.owner.register', ['myStores' => Store::where('owner_line_user_id', $c->line_user_id)->get()]);
    }

    public function storeRegistration(Request $r)
    {
        $c = $this->customer($r);
        $data = $r->validate([
            'name' => 'required|max:100', 'description' => 'nullable|max:500',
            'postal_code' => 'nullable|max:10', 'prefecture' => 'required|max:20', 'city' => 'required|max:50', 'address' => 'nullable|max:100',
            'tel' => 'required|max:20', 'representative_name' => 'required|max:50', 'representative_tel' => 'required|max:20',
            'business_license_number' => 'nullable|max:50',
            'bank_name' => 'required|max:50', 'bank_branch' => 'required_unless:bank_name,ゆうちょ銀行|nullable|max:50',
            'bank_account_type' => 'required|in:普通,当座', 'bank_account_number' => 'required_unless:bank_name,ゆうちょ銀行|nullable|digits:7',
            'bank_account_name' => 'required|max:50',
            'yucho_symbol' => 'required_if:bank_name,ゆうちょ銀行|nullable|digits:5', 'yucho_number' => 'required_if:bank_name,ゆうちょ銀行|nullable|digits_between:1,8',
            'publish' => 'required|in:common,original',
        ], [], [
            'name' => '店名', 'prefecture' => '都道府県', 'city' => '市区町村', 'tel' => '電話番号', 'representative_name' => '代表者名',
            'representative_tel' => '代表者電話番号', 'bank_name' => '銀行名', 'bank_branch' => '支店名', 'bank_account_number' => '口座番号（7桁）',
            'bank_account_name' => '口座名義', 'yucho_symbol' => 'ゆうちょ記号（5桁）', 'yucho_number' => 'ゆうちょ番号', 'publish' => '掲載方法',
        ]);
        $original = $data['publish'] === 'original';
        unset($data['publish']);
        $store = Store::create($data + [
            'slug' => 'tmp-'.Str::lower(Str::random(8)), 'owner_line_user_id' => $c->line_user_id,
            'commission_rate' => config('lab.default_commission_rate'),
            'is_listed_in_directory' => ! $original, 'wants_original' => $original,
        ]);
        $store->update(['slug' => "store-{$store->id}"]);
        $c->update(['name' => $data['representative_name']]);
        Inside::ok('app', "出店登録を受け付けました：「{$store->name}」（未承認・".($original ? 'オリジナル公式LINE希望' : '共通掲載').'）',
            "仮の slug: {$store->slug}\nオーナー: {$data['representative_name']}（LINEユーザーID {$c->line_user_id}）\n振込先: ".$store->maskedBankAccount()
            ."\n→ 次はオーナーが商品を登録。運営は管理画面で内容を確認して承認し、slug を決めます");
        return redirect()->route('liff.manage.menus', $store)->with('msg', '出店登録を受け付けました。続けて商品を登録してください。');
    }

    public function index(Request $r)
    {
        $c = $this->customer($r);
        $stores = Store::where('owner_line_user_id', $c->line_user_id)
            ->withCount(['menus', 'orders as waiting_count' => fn ($q) => $q->where('status', OrderStatus::Requested)])->get();
        return view('liff.owner.index', compact('stores'));
    }

    public function menus(Request $r, Store $store)
    {
        $this->own($r, $store);
        return view('liff.owner.menus', ['store' => $store, 'menus' => $store->menus()->orderBy('id')->get(),
            'templates' => MenuTemplate::where('is_active', true)->orderBy('sort_order')->get()->groupBy('category')]);
    }

    public function addMenu(Request $r, Store $store)
    {
        $this->own($r, $store);
        if ($r->filled('template_id')) {
            $t = MenuTemplate::findOrFail($r->input('template_id'));
            $menu = $store->menus()->create(['menu_template_id' => $t->id, 'name' => $t->name, 'price' => (int) ($r->input('price') ?: $t->reference_price)]);
        } else {
            $data = $r->validate(['name' => 'required|max:100', 'price' => 'required|integer|min:1'], [], ['name' => '商品名', 'price' => '価格']);
            $menu = $store->menus()->create($data);
        }
        Inside::info('app', "オーナーが「{$store->name}」に商品「{$menu->name}」".number_format($menu->price).'円 を登録しました'.($menu->menu_template_id ? '（テンプレートから）' : ''));
        return back()->with('msg', '追加しました');
    }

    public function toggleMenu(Request $r, Store $store, Menu $menu)
    {
        $this->own($r, $store);
        abort_unless($menu->store_id === $store->id, 404);
        $menu->update(['is_active' => ! $menu->is_active]);
        return back();
    }

    public function gifts(Request $r, Store $store)
    {
        $this->own($r, $store);
        return view('liff.owner.gifts', ['store' => $store, 'orders' => $store->orders()->with('customer')->latest('id')->get()]);
    }

    // 贈り物を受け取る画面（受け取ったあとは「受け取りました！」を表示）
    public function gift(Request $r, Store $store, Order $order)
    {
        $this->own($r, $store);
        abort_unless($order->store_id === $store->id, 404);
        return view('liff.owner.gift', ['store' => $store, 'order' => $order->load('customer')]);
    }

    public function receive(Request $r, Store $store, Order $order)
    {
        $this->own($r, $store);
        abort_unless($order->store_id === $store->id, 404);
        try { OrderService::receive($order); } catch (\RuntimeException $e) { return back()->with('msg', $e->getMessage()); }
        // 受け取ったら、送り主に通知（本番環境と同じ）
        Notifier::toCustomer($order, "[{$store->name}]\n贈り物「{$order->menu_name}」を受け取りました🍾\nお礼をお届けするまでしばらくお待ちください。");
        return redirect()->route('liff.manage.gift', [$store, $order]);
    }

    public function thankForm(Request $r, Store $store, Order $order)
    {
        $this->own($r, $store);
        return view('liff.owner.thank', ['store' => $store, 'order' => $order->load('customer')]);
    }

    public function thank(Request $r, Store $store, Order $order)
    {
        $this->own($r, $store);
        abort_unless($order->store_id === $store->id, 404);
        $r->validate(['video' => 'nullable|file|mimetypes:video/mp4,video/quicktime,video/webm|max:20480', 'thank_message' => 'nullable|max:300'],
            [], ['video' => 'お礼動画', 'thank_message' => 'メッセージ']);
        if (! $r->hasFile('video') && ! $r->filled('thank_message')) return back()->withErrors(['video' => 'お礼動画かメッセージのどちらかを入れてください。']);

        // 動画は「公開しない場所」（storage/app/private/thank-videos/{注文ID}/）に保存。見るときは署名付きURLを発行する
        $path = $r->hasFile('video') ? $r->file('video')->store("thank-videos/{$order->id}") : null;
        try { OrderService::thank($order, $path, $r->input('thank_message')); } catch (\RuntimeException $e) { return back()->with('msg', $e->getMessage()); }

        $thanksLiff = Setting::get('platform_liff_thanks');
        Notifier::toCustomer($order, "[{$store->name}]からお礼が届きました🎬\n".($r->input('thank_message') ? '「'.$r->input('thank_message')."」\n" : '')
            .'お礼一覧から見られます'.($thanksLiff ? "\nhttps://liff.line.me/{$thanksLiff}" : ''));
        return redirect()->route('liff.manage.gifts', $store)->with('msg', 'お礼を送りました');
    }
}
