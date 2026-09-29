<?php

namespace App\Http\Controllers\Liff;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Support\Inside;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    // 共通アプリ：お店をさがす（共通掲載のお店だけ）
    public function index(Request $r)
    {
        $stores = Store::where('is_approved', true)->where('is_listed_in_directory', true)
            ->when($r->q, fn ($q) => $q->where('name', 'like', "%{$r->q}%"))
            ->withCount(['menus' => fn ($q) => $q->where('is_active', true)])->withMin(['menus' => fn ($q) => $q->where('is_active', true)], 'price')
            ->orderBy('id')->get();
        Inside::info('app', "共通アプリ：お店の一覧を表示しました（{$stores->count()}件）", '「承認済み」かつ「共通アプリの店舗一覧に掲載する」のお店だけが並びます');
        return view('liff.shops', compact('stores'));
    }

    // お店のギフト画面（共通アプリからも、店舗の公式LINE・QRからも、ここに来る）
    public function show(Request $r, string $slug)
    {
        $store = Store::where('slug', $slug)->first();
        if (! $store) {
            Inside::ng('app', "LIFF画面: slug「{$slug}」の店舗はありません", 'LIFF のエンドポイントURL に入れた slug が正しいか確認しましょう');
            return response()->view('liff.message', ['title' => '店舗が見つかりません', 'message' => "「{$slug}」というお店はありません。"], 404);
        }
        if (! $store->is_approved) {
            Inside::info('app', "LIFF画面:「{$store->name}」はまだ承認されていないので表示しません");
            return view('liff.message', ['title' => '準備中', 'message' => "{$store->name} は現在準備中です。"]);
        }
        // どこから来たか：?via=app なら共通アプリ、それ以外（店舗のLIFF・QR）は店舗の公式LINE
        $route = $r->query('via') === 'app' ? 'common' : 'original';
        $r->session()->put("route.{$store->id}", $route);
        $opened = $r->session()->get('opened_liff');
        if ($route === 'original' && $opened && $store->liff_id && $opened !== $store->liff_id && $r->query('mock_liff')) {
            Inside::ng('app', "LIFF {$opened} から「{$store->name}」の画面が開かれました", "この店舗の LIFF ID は {$store->liff_id} です。\n別のお店の LIFF のエンドポイントURL が、この店の slug になっていませんか？");
        } else {
            Inside::info('app', "ギフト画面「{$store->name}」を表示しました（".($route === 'common' ? '共通アプリから' : '店舗の公式LINE／QRから').'）');
        }
        return view('liff.store', [
            'store' => $store, 'route' => $route,
            'menus' => $store->menus()->where('is_active', true)->orderBy('price')->get(),
            'needsId' => $store->require_id_verification && ! $r->attributes->get('customer')?->id_verified_at,
        ]);
    }
}
