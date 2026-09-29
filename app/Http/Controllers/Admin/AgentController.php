<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Order;
use App\Support\Inside;
use Illuminate\Http\Request;

// =====================================================
// 代理店
//   報酬額 ＝ 紐づけ店舗の「売上」× 報酬率（手数料ではなく売上に対して）
//   2次代理店の報酬率は 0% 表示（取り分は1次代理店から受け取るため）。2次経由の店舗の売上は1次に計上
// =====================================================
class AgentController extends Controller
{
    public function index(Request $r)
    {
        $agents = Agent::with('parent')->withCount('stores')
            ->when($r->q, fn ($q) => $q->where('name', 'like', "%{$r->q}%")->orWhere('referral_code', $r->q))
            ->orderByRaw('COALESCE(parent_id, id), parent_id IS NOT NULL, id')->get();
        return view('admin.agents.index', compact('agents'));
    }

    public function create() { return view('admin.agents.form', ['agent' => new Agent(['is_active' => true, 'reward_rate' => 3]), 'parents' => Agent::whereNull('parent_id')->get()]); }

    public function store(Request $r)
    {
        $data = $this->validated($r);
        $agent = Agent::create($data + ['referral_code' => Agent::newReferralCode()]);
        Inside::info('admin', "代理店「{$agent->name}」を登録しました（".($agent->parent_id ? '2次' : '1次')."・紹介コード {$agent->referral_code}）");
        return redirect()->route('admin.agents.index')->with('msg', "登録しました。紹介コード {$agent->referral_code}");
    }

    public function show(Request $r, Agent $agent)
    {
        $year = (int) $r->input('year', now()->year);
        $ids = $agent->rewardStoreIds();
        $orders = Order::whereIn('store_id', $ids)->whereIn('status', OrderStatus::settled())->whereYear('created_at', $year);
        $sales = (int) (clone $orders)->sum('amount');
        $monthly = (clone $orders)->selectRaw("CAST(strftime('%m', created_at) AS INTEGER) AS m, SUM(amount) AS sales")->groupBy('m')->pluck('sales', 'm');
        return view('admin.agents.show', [
            'agent' => $agent->load(['stores', 'children.stores', 'parent']), 'year' => $year, 'sales' => $sales,
            'reward' => $agent->isPrimary() ? (int) round($sales * $agent->reward_rate / 100) : 0,
            'monthly' => $monthly, 'directCount' => $agent->stores->count(),
            'viaSecondCount' => $agent->isPrimary() ? $agent->children->sum(fn ($c) => $c->stores->count()) : 0,
        ]);
    }

    public function edit(Agent $agent) { return view('admin.agents.form', ['agent' => $agent, 'parents' => Agent::whereNull('parent_id')->where('id', '<>', $agent->id)->get()]); }

    public function update(Request $r, Agent $agent)
    {
        $agent->update($this->validated($r));
        return redirect()->route('admin.agents.show', $agent)->with('msg', '更新しました');
    }

    public function destroy(Agent $agent)
    {
        if ($agent->stores()->exists() || $agent->children()->exists()) return back()->with('msg', '店舗や2次代理店が紐づいている代理店は削除できません。');
        $agent->delete();
        return redirect()->route('admin.agents.index')->with('msg', '削除しました');
    }

    private function validated(Request $r): array
    {
        $data = $r->validate(['name' => 'required|max:100', 'company' => 'nullable|max:100', 'parent_id' => 'nullable|exists:agents,id', 'reward_rate' => 'required|numeric|min:0|max:100'],
            [], ['name' => '代理店名', 'reward_rate' => '報酬率']);
        if (! empty($data['parent_id'])) $data['reward_rate'] = 0;   // 2次は 0%
        return $data + ['company' => (string) $r->input('company'), 'is_active' => $r->boolean('is_active', true)];
    }
}
