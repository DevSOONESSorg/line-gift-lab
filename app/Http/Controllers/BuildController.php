<?php

namespace App\Http\Controllers;

use App\Services\BuildGuide;
use App\Services\BuildScenario;
use App\Models\Store;
use Illuminate\Http\Request;

// 構築ナビ：どのお店を構築中かを覚え、手順の一覧を表示する
class BuildController extends Controller
{
    public function index(Request $r)
    {
        // まだ選んでいなければ、最初の課題のお店を選んでおく（作り直しはしない）
        if (! Store::find(session('build.store')) && ($id = BuildScenario::storeId(array_key_first(BuildScenario::SCENARIOS))) && Store::find($id)) {
            session(['build.store' => $id]);
        }
        $guide = BuildGuide::current();
        return view('build.index', ['guide' => $guide, 'steps' => $guide?->steps() ?? [], 'scenarios' => BuildScenario::SCENARIOS]);
    }

    // お店を選ぶ＝そのお店を「スタート地点」に作り直して、新しく構築を始める
    public function select(Request $r)
    {
        $key = (string) $r->input('scenario');
        abort_unless(isset(BuildScenario::SCENARIOS[$key]), 404);
        $store = BuildScenario::reset($key);
        session()->forget('build');
        session(['build.store' => $store->id, 'mock_account' => 'personal']);
        return redirect()->route('build')->with('msg', "「{$store->name}」を最初の状態に戻して、新しく構築を始めました。お客さんのスマホにお店の公式LINEが出ています（リッチメニューはまだ押せません）。");
    }

    // 右下のナビの中身だけ（数秒ごとに読み直す）
    public function nav()
    {
        $guide = BuildGuide::current();
        return $guide ? view('build._nav-body', ['guide' => $guide]) : response('');
    }

    public function stop(Request $r)
    {
        $r->session()->forget('build');
        return redirect()->route('build');
    }
}
