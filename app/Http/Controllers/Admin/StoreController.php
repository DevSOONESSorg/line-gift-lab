<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Store;
use App\Models\StoreCommissionRate;
use App\Services\Line\LineClient;
use App\Services\PublicUrl;
use App\Support\Inside;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StoreController extends Controller
{
    public function index(Request $r)
    {
        $stores = Store::withCount('orders')
            ->when($r->q, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$r->q}%")->orWhere('slug', 'like', "%{$r->q}%")))
            ->when($r->status === 'pending', fn ($q) => $q->where('is_approved', false))
            ->when($r->status === 'approved', fn ($q) => $q->where('is_approved', true))
            ->latest('id')->paginate(20)->withQueryString();
        return view('admin.stores.index', compact('stores'));
    }

    // コースB用：管理画面から直接店舗を作る
    public function store(Request $r)
    {
        $data = $r->validate(['name' => 'required|max:100', 'slug' => ['required', 'regex:/^[a-z0-9][a-z0-9_-]{1,40}$/', 'unique:stores,slug']],
            [], ['name' => '店舗名', 'slug' => 'スラッグ']);
        $store = Store::create($data + ['is_approved' => true, 'is_listed_in_directory' => false, 'wants_original' => true,
            'commission_rate' => config('lab.default_commission_rate')]);
        Inside::info('admin', "管理画面から店舗「{$store->name}」（{$store->slug}）を作成しました");
        return redirect()->route('admin.stores.edit', $store);
    }

    public function show(Store $store)
    {
        $store->load(['menus', 'agent', 'owner']);
        $settled = $store->orders()->whereIn('status', OrderStatus::settled());
        $sales = (int) (clone $settled)->sum('amount');
        $commission = (int) (clone $settled)->sum('commission');
        return view('admin.stores.show', compact('store', 'sales', 'commission'));
    }

    public function edit(Store $store)
    {
        $months = collect(range(0, 11))->map(fn ($i) => now()->startOfMonth()->addMonths($i));
        $rates = $store->monthlyRates()->get()->keyBy(fn ($x) => "{$x->year}-{$x->month}");
        $agents = Agent::with('parent')->orderByRaw('COALESCE(parent_id, id), parent_id IS NOT NULL, id')->get();
        $tunnel = PublicUrl::tunnel();
        return view('admin.stores.edit', compact('store', 'months', 'rates', 'agents') + [
            'webhookLocal' => PublicUrl::local()."/api/webhook/line/store/{$store->slug}",
            'webhookPublic' => $tunnel ? "{$tunnel}/api/webhook/line/store/{$store->slug}" : '',
            'liffLocal' => PublicUrl::local()."/liff/s/{$store->slug}",
            'liffPublic' => $tunnel ? "{$tunnel}/liff/s/{$store->slug}" : '',
            'checks' => session('checks'),
        ]);
    }

    public function update(Request $r, Store $store)
    {
        $data = $r->validate([
            'name' => 'required|max:100',
            'slug' => ['required', 'regex:/^[a-z0-9][a-z0-9_-]{1,40}$/', Rule::unique('stores')->ignore($store->id)],
            'description' => 'nullable|max:1000', 'website_url' => 'nullable|url',
            'postal_code' => 'nullable|max:10', 'prefecture' => 'nullable|max:20', 'city' => 'nullable|max:50', 'address' => 'nullable|max:100',
            'tel' => 'nullable|max:20', 'representative_name' => 'nullable|max:50', 'representative_tel' => 'nullable|max:20',
            'business_license_number' => 'nullable|max:50',
            'bank_name' => 'nullable|max:50', 'bank_branch' => 'nullable|max:50', 'bank_account_type' => 'nullable|in:普通,当座',
            'bank_account_number' => 'nullable|digits:7', 'bank_account_name' => 'nullable|max:50',
            'yucho_symbol' => 'nullable|digits:5', 'yucho_number' => 'nullable|digits_between:1,8',
            'agent_id' => 'nullable|exists:agents,id', 'commission_rate' => 'required|numeric|min:0|max:100',
            'liff_id' => 'nullable|max:50', 'line_messaging_channel_id' => 'nullable|max:20', 'line_official_account_id' => 'nullable|max:30',
        ], [], ['slug' => 'スラッグ', 'bank_account_number' => '口座番号', 'commission_rate' => 'デフォルト手数料率']);

        $old = $store->replicate();
        $store->fill($data);
        foreach (['is_approved', 'is_listed_in_directory', 'require_id_verification', 'auto_reply_enabled'] as $flag) $store->$flag = $r->boolean($flag);
        // シークレットとトークンは「空欄なら変えない」
        if ($r->filled('line_messaging_channel_secret')) $store->line_messaging_channel_secret = trim($r->input('line_messaging_channel_secret'));
        if ($r->filled('line_messaging_channel_access_token')) $store->line_messaging_channel_access_token = trim($r->input('line_messaging_channel_access_token'));
        $store->save();

        $changes = collect($store->getChanges())->except(['updated_at'])->keys()
            ->map(fn ($k) => in_array($k, ['line_messaging_channel_secret', 'line_messaging_channel_access_token']) ? "{$k}（暗号化して保存）" : "{$k}: ".($old->$k === null ? '空' : var_export($old->$k, true)).' → '.var_export($store->$k, true));
        if ($store->wasChanged('slug')) $changes->push('★ slug を変えたので、LIFF のエンドポイントURLと Webhook URL も変える必要があります');
        Inside::info('admin', "店舗「{$store->name}」を更新しました", $changes->join("\n") ?: '（変更なし）');
        return redirect()->route('admin.stores.edit', $store)->with('msg', '更新しました');
    }

    public function destroy(Store $store)
    {
        if ($store->orders()->exists()) return back()->with('msg', '注文がある店舗は削除できません（売上の記録が消えてしまうため）。承認を取り消してください。');
        $store->delete();
        Inside::info('admin', "店舗「{$store->name}」を削除しました");
        return redirect()->route('admin.stores.index')->with('msg', '削除しました');
    }

    public function approve(Store $store)
    {
        $store->update(['is_approved' => true]);
        Inside::ok('admin', "店舗「{$store->name}」を承認しました");
        return back()->with('msg', '承認しました');
    }

    public function reject(Store $store)
    {
        $store->update(['is_approved' => false]);
        Inside::info('admin', "店舗「{$store->name}」の承認を取り消しました");
        return back()->with('msg', '承認を取り消しました');
    }

    // 設定整合性チェック
    public function check(Store $store)
    {
        $checks = [];
        $add = function (string $label, bool $ok, string $hint = '') use (&$checks) { $checks[] = compact('label', 'ok', 'hint'); };

        if (! $store->hasOwnLiff()) $add('LIFF ID', true, '未設定 — 共通のLIFFが使われます（共通掲載だけのお店はこれでOK）');
        else $add('LIFF ID の形', (bool) preg_match('/^\d{10}-[A-Za-z0-9]{8}$/', $store->liff_id), '「10桁の数字-8文字」の形です（LINEログインチャネル → LIFF タブ）');

        if (! $store->line_messaging_channel_id && ! $store->line_messaging_channel_access_token) {
            $add('Messaging API', true, '未設定 — お知らせは共通チャネルから送信されます（フォールバック）');
        } else {
            $add('チャネルID の形', (bool) preg_match('/^\d{10}$/', (string) $store->line_messaging_channel_id), '10桁の数字（Messaging APIチャネルの Basic settings）');
            $add('チャネルシークレット', (bool) preg_match('/^[0-9a-f]{32}$/', (string) $store->line_messaging_channel_secret), '32文字の英数字');
            $add('アクセストークン', filled($store->line_messaging_channel_access_token), 'Messaging API タブで「発行」');
            $add('公式アカウントID の形', (bool) preg_match('/^@[0-9a-z]{3,}/i', (string) $store->line_official_account_id), '@ から始まるベーシックID');
            if ($store->line_messaging_channel_access_token) {
                $line = LineClient::forStore($store);
                $info = $line->botInfo();
                if (! $info['ok']) {
                    $add('トークンで LINE に接続できる', false, "LINEの答え: {$info['status']} → トークンを再発行して入れ直しましょう");
                } else {
                    $add('トークンで LINE に接続できる', true, "このトークンは「{$info['data']['displayName']}」{$info['data']['basicId']} のもの");
                    $v = $line->verifyToken();
                    if ($v['ok']) $add('トークンとチャネルIDが同じチャネル', (string) $v['data']['client_id'] === (string) $store->line_messaging_channel_id,
                        "トークンはチャネル {$v['data']['client_id']} のもの。LINEログインチャネルのIDを入れていませんか？");
                    $add('トークンと公式アカウントIDが同じアカウント', mb_strtolower($info['data']['basicId']) === mb_strtolower((string) $store->line_official_account_id), '別のお店の値が混ざっていませんか？');
                }
            }
        }
        $allOk = collect($checks)->every('ok');
        ($allOk ? [Inside::class, 'ok'] : [Inside::class, 'ng'])('admin', "「{$store->name}」の設定整合性チェック → ".($allOk ? 'すべてOK' : 'NGあり'),
            collect($checks)->map(fn ($c) => ($c['ok'] ? 'OK  ' : 'NG  ').$c['label'])->join("\n"));
        return redirect()->route('admin.stores.edit', $store)->with('checks', $checks)->withFragment('line');
    }

    // テスト送信（送信先の userId を指定）
    public function testLine(Request $r, Store $store)
    {
        $r->validate(['line_id' => ['required', 'regex:/^U[0-9a-f]{32}$/']], [], ['line_id' => '送信先 LINE userId']);
        $client = $store->hasOwnMessagingChannel() ? LineClient::forStore($store) : LineClient::platform();
        $res = $client->push($r->input('line_id'), "【テスト送信】{$store->name} の公式LINEからのテストです。");
        return redirect()->route('admin.stores.edit', $store)->with('msg', "テスト送信：{$res['status']} ".($res['ok'] ? '送信しました（届いたかはスマホで確認。友だちでないと届きません）' : ($res['data']['message'] ?? '')))->withFragment('line');
    }

    // 月別手数料率
    public function saveRates(Request $r, Store $store)
    {
        foreach ((array) $r->input('rates', []) as $row) {
            $key = ['store_id' => $store->id, 'year' => (int) $row['year'], 'month' => (int) $row['month']];
            if (($row['rate'] ?? '') === '' || $row['rate'] === null) StoreCommissionRate::where($key)->delete();
            else StoreCommissionRate::updateOrCreate($key, ['rate' => (float) $row['rate']]);
        }
        Inside::info('admin', "店舗「{$store->name}」の月別手数料率を保存しました");
        return redirect()->route('admin.stores.edit', $store)->with('msg', '月別手数料率を保存しました')->withFragment('rates');
    }
}
