<?php

namespace App\Http\Controllers;

use App\Models\Mock\Account;
use App\Models\Store;
use App\Services\BuildGuide;
use Illuminate\Http\Request;

// 構築ナビ：どのお店を構築中かを覚え、手順の一覧を表示する
class BuildController extends Controller
{
    public function index(Request $r)
    {
        // 管理画面の「構築ナビで進める」から来たとき（?store=ID）
        if ($r->query('store') && Store::find($r->query('store'))) { $r->session()->put('build.store', (int) $r->query('store')); return redirect()->route('build'); }
        // まだ選んでいなければ、オリジナル希望でまだつながっていないお店（課題のお店）を選んでおく
        if (! session('build.store') && ($s = Store::where('wants_original', true)->whereNull('liff_id')->orderBy('id')->first())) session(['build.store' => $s->id]);
        $guide = BuildGuide::current();
        return view('build.index', [
            'guide' => $guide, 'steps' => $guide?->steps() ?? [],
            'stores' => Store::orderByDesc('wants_original')->orderBy('id')->get(),
        ]);
    }

    public function select(Request $r)
    {
        $store = Store::findOrFail($r->input('store_id'));
        $r->session()->put('build.store', $store->id);
        return redirect()->to($r->input('back') ?: route('build'))->with('msg', "「{$store->name}」の構築ナビを始めました");
    }

    public function stop(Request $r)
    {
        $r->session()->forget('build.store');
        return redirect()->to($r->input('back') ?: route('build'));
    }
}
