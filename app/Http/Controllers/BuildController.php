<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\BuildGuide;
use App\Services\BuildScenario;
use Illuminate\Http\Request;

// 構築ナビ：どのお店を構築中かを覚え、手順の一覧を表示する
class BuildController extends Controller
{
    public function index(Request $r)
    {
        $guide = BuildGuide::current();
        // プルダウンに出す、お店ごとの進み具合（未着手／構築中／完成）
        $status = [];
        foreach (array_keys(BuildScenario::SCENARIOS) as $k) {
            $store = Store::find(BuildScenario::storeId($k));
            if (! $store) { $status[$k] = ['label' => '未着手', 'done' => 0, 'total' => 0]; continue; }
            $steps = (new BuildGuide($store))->steps();
            $done = collect($steps)->where('state', 'done')->count();
            $status[$k] = ['label' => $done === count($steps) ? '完成' : "構築中 {$done}/".count($steps), 'done' => $done, 'total' => count($steps)];
        }
        return view('build.index', ['guide' => $guide, 'steps' => $guide?->steps() ?? [], 'scenarios' => BuildScenario::SCENARIOS, 'status' => $status]);
    }

    // プルダウンで選ぶ：はじめてのお店はスタート地点を作る（お客さんのスマホに公式LINEが追加される）。構築済み・構築中のお店は続きから
    public function select(Request $r)
    {
        $key = (string) $r->input('scenario');
        abort_unless(isset(BuildScenario::SCENARIOS[$key]), 404);
        $isNew = ! Store::find(BuildScenario::storeId($key));
        $store = BuildScenario::start($key);
        session(['build.store' => $store->id, 'mock_account' => 'personal']);
        return redirect()->route('build')->with('msg', $isNew
            ? "「{$store->name}」の構築を始めました。お客さんのスマホに、お店の公式LINEが追加されました（リッチメニューは手順10が終わるまで押せません）。"
            : "「{$store->name}」に切り替えました。続きから構築できます。");
    }

    // 構築履歴をリセット：課題のお店をすべて消して、最初の状態に戻す
    public function resetAll(Request $r)
    {
        BuildScenario::resetAll();
        $r->session()->forget('build');
        return redirect()->route('build')->with('msg', '構築履歴をリセットしました。課題のお店はすべて消え、お客さんのスマホからも公式LINEがなくなりました。プルダウンでお店を選ぶと、はじめから構築できます。');
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
