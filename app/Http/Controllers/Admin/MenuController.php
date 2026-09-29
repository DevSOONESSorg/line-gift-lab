<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuTemplate;
use App\Models\Store;
use Illuminate\Http\Request;

// 店舗の商品（ふだんはオーナーが店舗管理から登録。管理画面からは代わりに入れるとき）
class MenuController extends Controller
{
    public function create(Store $store)
    {
        return view('admin.menus.form', ['store' => $store, 'menu' => new Menu(['is_active' => true]), 'templates' => MenuTemplate::where('is_active', true)->orderBy('sort_order')->get()]);
    }

    public function store(Request $r, Store $store)
    {
        $store->menus()->create($this->validated($r));
        return redirect()->route('admin.stores.show', $store)->with('msg', '商品を追加しました');
    }

    public function edit(Store $store, Menu $menu)
    {
        return view('admin.menus.form', ['store' => $store, 'menu' => $menu, 'templates' => MenuTemplate::where('is_active', true)->orderBy('sort_order')->get()]);
    }

    public function update(Request $r, Store $store, Menu $menu)
    {
        $menu->update($this->validated($r));
        return redirect()->route('admin.stores.show', $store)->with('msg', '商品を更新しました');
    }

    public function destroy(Store $store, Menu $menu)
    {
        $menu->delete();
        return redirect()->route('admin.stores.show', $store)->with('msg', '商品を削除しました');
    }

    private function validated(Request $r): array
    {
        $data = $r->validate(['name' => 'required|max:100', 'price' => 'required|integer|min:1', 'menu_template_id' => 'nullable|exists:menu_templates,id'], [], ['name' => '商品名', 'price' => '価格']);
        return $data + ['is_active' => $r->boolean('is_active')];
    }
}
