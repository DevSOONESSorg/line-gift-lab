<?php

namespace App\Http\Controllers\Mock;

use App\Http\Controllers\Controller;
use App\Models\Mock\Account;
use App\Models\Mock\Channel;
use App\Models\Mock\LiffApp;
use App\Models\Mock\OfficialAccount;
use App\Models\Mock\Provider;
use App\Services\MockLine\MockLine;
use App\Support\Inside;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// 疑似 LINE Developers Console（developers.line.biz の代わり）
class DevelopersController extends Controller
{
    public const TYPES = ['messaging' => 'Messaging API', 'login' => 'LINEログイン'];

    private function me(Request $r): string { return $r->session()->get('mock_account', 'personal'); }

    public function index(Request $r)
    {
        return view('mock.developers.index', ['providers' => Provider::of($this->me($r))]);
    }

    public function provider(Request $r, Provider $provider)
    {
        abort_unless($provider->hasMember($this->me($r)), 403, 'このプロバイダーのメンバーではありません。');
        $channels = $provider->channels()->orderBy('id')->get()->each(fn ($c) => $c->hasRole = (bool) $c->roleOf($this->me($r)));
        return view('mock.developers.provider', ['provider' => $provider, 'channels' => $channels, 'types' => self::TYPES, 'creating' => $r->query('create')]);
    }

    public function createChannel(Request $r, Provider $provider)
    {
        abort_unless($provider->hasMember($this->me($r)), 403);
        if ($r->input('type') !== 'login') {
            return back()->with('msg', 'Messaging APIチャネルは、ここでは作れません。LINE Official Account Manager で「Messaging APIを利用する」から作ります。');
        }
        $max = config('lab.build.channel_name_max');
        $data = $r->validate([
            'country' => 'required', 'name' => "required|max:{$max}", 'description' => 'required|max:500', 'app_type' => 'required|in:web',
            'email' => 'required|email|max:100', 'privacy_url' => 'nullable|url|starts_with:https://|max:500', 'terms_url' => 'nullable|url|starts_with:https://|max:500',
        ], [
            'required' => ':attribute は入力必須項目です', 'name.max' => "チャネル名は{$max}文字以内で入力してください",
            'app_type.required' => 'アプリタイプで「ウェブアプリ」を選んでください', 'email' => '有効なメールアドレスを入力してください',
            'starts_with' => ':attribute は有効なHTTPS URLを入力してください', 'url' => ':attribute は有効なHTTPS URLを入力してください',
        ], ['country' => '所在国・地域', 'name' => 'チャネル名', 'description' => 'チャネル説明', 'email' => 'メールアドレス', 'privacy_url' => 'プライバシーポリシーURL', 'terms_url' => 'サービス利用規約URL']);
        $ch = MockLine::createLoginChannel($provider->id, $data['name'], $this->me($r), $data['description'], [
            'email' => $data['email'], 'privacy_url' => (string) ($data['privacy_url'] ?? ''), 'terms_url' => (string) ($data['terms_url'] ?? ''),
            'country' => $data['country'], 'two_factor' => $r->input('two_factor') === '1',
        ]);
        if ($sid = $r->session()->get('build.store')) $r->session()->put("build.login.{$sid}", $ch->id);   // 構築ナビ用
        return redirect()->route('mock.developers.channel.show', $ch)->with('msg', 'チャネルを作成しました');
    }

    private function guard(Request $r, Channel $ch): void
    {
        $me = $this->me($r);
        if (! $ch->provider->hasMember($me) || ! $ch->roleOf($me)) {
            abort(403, '「'.Account::find($me)->name."」には、このチャネル（{$ch->name}）を開く権限がありません。自分が作っていないチャネルは「権限なし」になります（仕様です）。チャネルの権限設定（Roles）で追加してもらう必要があります。");
        }
        view()->share(['channel' => $ch, 'provider' => $ch->provider, 'types' => self::TYPES]);
    }

    public function channel(Request $r, Channel $channel)
    {
        $this->guard($r, $channel);
        $roles = DB::connection('mockline')->table('channel_roles')->join('accounts', 'accounts.id', '=', 'channel_roles.account_id')->where('channel_id', $channel->id)->get();
        // リンクできる公式アカウント：同じプロバイダーに Messaging API チャネルがあるもの（本物と同じ）
        $linkable = OfficialAccount::whereIn('id', Channel::where(['type' => 'messaging', 'provider_id' => $channel->provider_id])->pluck('official_account_id'))->orderBy('basic_id')->get();
        return view('mock.developers.channel', ['tab' => $r->query('tab', 'basic'), 'liffs' => $channel->liffApps, 'roles' => $roles,
            'accounts' => Account::all(), 'verify' => session('verify'), 'linkable' => $linkable, 'editLiff' => $r->query('liff') ? $channel->liffApps->firstWhere('liff_id', $r->query('liff')) : null]);
    }

    private function back(Channel $ch, string $tab, ?string $msg = null)
    {
        return redirect()->route('mock.developers.channel.show', [$ch, 'tab' => $tab])->with('msg', $msg);
    }

    public function reissueSecret(Request $r, Channel $channel) { $this->guard($r, $channel); MockLine::reissueSecret($channel); return $this->back($channel, 'basic', 'チャネルシークレットを再発行しました。古いシークレットはもう使えません。'); }
    public function issueToken(Request $r, Channel $channel) { $this->guard($r, $channel); MockLine::issueToken($channel); return $this->back($channel, 'messaging', 'チャネルアクセストークン（長期）を発行しました'); }

    public function saveWebhook(Request $r, Channel $channel)
    {
        $this->guard($r, $channel);
        $url = trim((string) $r->input('webhook_url'));
        $channel->update(['webhook_url' => $url] + ($url !== $channel->webhook_url ? ['webhook_verified_at' => null] : []));
        Inside::info('line', "Webhook URLを登録しました（{$channel->channel_id}）", $channel->webhook_url ?: '(空)');
        return $this->back($channel, 'messaging', 'Webhook URLを更新しました');
    }

    public function verify(Request $r, Channel $channel)
    {
        $this->guard($r, $channel);
        $result = MockLine::sendWebhook($channel, [], true);
        $channel->update(['webhook_verified_at' => ($result['ok'] ?? false) ? now() : null]);
        return $this->back($channel, 'messaging')->with('verify', $result);
    }

    public function toggleWebhook(Request $r, Channel $channel)
    {
        $this->guard($r, $channel);
        $channel->update(['use_webhook' => ! $channel->use_webhook]);
        Inside::info('line', 'Webhookの利用 を '.($channel->use_webhook ? 'ON' : 'OFF')." にしました（{$channel->channel_id}）");
        return $this->back($channel, 'messaging');
    }

    public function publish(Request $r, Channel $channel)
    {
        $this->guard($r, $channel);
        $channel->update(['is_published' => ! $channel->is_published]);
        Inside::info('line', "LINEログインチャネル {$channel->channel_id} を「".($channel->is_published ? '公開' : '開発中').'」にしました',
            '本物では「開発中」のあいだ、チャネルの管理者・テスター以外は LIFF を開けません（本番の店舗オンボーディングで忘れやすいポイント）');
        return $this->back($channel, 'basic');
    }

    // チャネル基本設定の編集（メール・プライバシーポリシー・所在国・2要素認証）
    public function saveBasic(Request $r, Channel $channel)
    {
        $this->guard($r, $channel);
        $data = $r->validate([
            'email' => 'required|email|max:100', 'country' => 'required',
            'privacy_url' => 'nullable|url|starts_with:https://|max:500', 'terms_url' => 'nullable|url|starts_with:https://|max:500',
        ], ['required' => ':attribute は入力必須項目です', 'email' => '有効なメールアドレスを入力してください', 'url' => ':attribute は有効なHTTPS URLを入力してください', 'starts_with' => ':attribute は有効なHTTPS URLを入力してください'],
            ['email' => 'メールアドレス', 'country' => '所在国・地域', 'privacy_url' => 'プライバシーポリシーURL', 'terms_url' => 'サービス利用規約URL']);
        $channel->update(['email' => $data['email'], 'country' => $data['country'], 'privacy_url' => (string) ($data['privacy_url'] ?? ''),
            'terms_url' => (string) ($data['terms_url'] ?? '')] + ($channel->type === 'login' ? ['two_factor' => $r->input('two_factor') === '1'] : []));
        Inside::info('line', "チャネル {$channel->channel_id} の基本設定を変更しました",
            "メール: {$channel->email}\nプライバシーポリシー: ".($channel->privacy_url ?: '（空）').($channel->type === 'login' ? "\n2要素認証の必須化: ".($channel->two_factor ? 'ON' : 'OFF') : ''));
        return $this->back($channel, 'basic', '基本設定を更新しました');
    }

    // 友だち追加オプション：リンクされたLINE公式アカウント（同じプロバイダーの Messaging API チャネルを持つ公式アカウントから選ぶ）
    public function saveLinkedOa(Request $r, Channel $channel)
    {
        $this->guard($r, $channel);
        abort_unless($channel->type === 'login', 404);
        $oaId = $r->input('linked_oa_id') ?: null;
        abort_if($oaId && ! Channel::where(['type' => 'messaging', 'provider_id' => $channel->provider_id, 'official_account_id' => $oaId])->exists(), 422, 'そのアカウントは選べません');
        $channel->update(['linked_oa_id' => $oaId]);
        $oa = $channel->fresh()->linkedOa;
        Inside::info('line', "LINEログインチャネル {$channel->channel_id} のリンクされたLINE公式アカウントを「".($oa ? "{$oa->basic_id} {$oa->name}" : '–').'」にしました',
            'LIFF の友だち追加オプションは、ここでリンクした公式アカウントの友だち追加をすすめます。未設定だと友だち追加オプションが効きません');
        return $this->back($channel, 'basic', 'リンクされたLINE公式アカウントを更新しました');
    }

    public function addLiff(Request $r, Channel $channel)
    {
        $this->guard($r, $channel);
        if ($channel->type !== 'login') return $this->back($channel, 'basic', 'LIFFはLINEログインチャネルに追加します');
        $endpoint = trim((string) $r->input('endpoint_url'));
        $again = fn ($m) => redirect()->route('mock.developers.channel.show', [$channel, 'tab' => 'liff', 'add' => 1])->with('msg', $m);
        if (! $r->filled('name')) return $again('LIFFアプリ名を入れてください');
        if (! in_array($r->input('size'), ['Compact', 'Tall', 'Full'], true)) return $again('サイズを選んでください');
        if (! preg_match('#^https?://#', $endpoint)) return $again('エンドポイントURLは http:// か https:// で始まるURLを入れてください');
        $liff = MockLine::addLiff($channel, trim($r->input('name')), $endpoint, $r->input('size'),
            implode(' ', (array) $r->input('scopes', ['openid'])), in_array($r->input('bot_prompt'), ['normal', 'aggressive', '1'], true));
        return $this->back($channel, 'liff', "LIFFアプリを追加しました。LIFF ID: {$liff->liff_id} ／ LIFF URL: https://liff.line.me/{$liff->liff_id}");
    }

    public function updateLiff(Request $r, Channel $channel, string $liffId)
    {
        $this->guard($r, $channel);
        $liff = LiffApp::where(['liff_id' => $liffId, 'channel_id' => $channel->id])->firstOrFail();
        $upd = array_filter([
            'endpoint_url' => $r->filled('endpoint_url') ? trim((string) $r->input('endpoint_url')) : null,
            'name' => $r->filled('name') ? trim((string) $r->input('name')) : null,
            'size' => in_array($r->input('size'), ['Compact', 'Tall', 'Full'], true) ? $r->input('size') : null,
        ], fn ($v) => $v !== null);
        if ($r->has('scopes_sent')) $upd['scopes'] = implode(' ', (array) $r->input('scopes', ['openid']));
        if ($r->has('bot_prompt')) $upd['bot_prompt'] = in_array($r->input('bot_prompt'), ['normal', 'aggressive'], true);
        $liff->update($upd);
        Inside::info('line', "LIFF {$liffId} の設定を変更しました", collect($upd)->map(fn ($v, $k) => "{$k}: ".var_export($v, true))->join("\n"));
        return redirect()->route('mock.developers.channel.show', [$channel, 'tab' => 'liff', 'liff' => $liffId])->with('msg', 'LIFFアプリを更新しました');
    }

    public function deleteLiff(Request $r, Channel $channel, string $liffId)
    {
        $this->guard($r, $channel);
        LiffApp::where(['liff_id' => $liffId, 'channel_id' => $channel->id])->delete();
        return $this->back($channel, 'liff', '削除しました');
    }

    public function addRole(Request $r, Channel $channel)
    {
        $this->guard($r, $channel);
        $acc = Account::find($r->input('account_id'));
        if ($acc && ! $channel->roleOf($acc->id)) {
            DB::connection('mockline')->table('channel_roles')->insert(['channel_id' => $channel->id, 'account_id' => $acc->id, 'role' => 'admin']);
            DB::connection('mockline')->table('provider_members')->insertOrIgnore(['provider_id' => $channel->provider_id, 'account_id' => $acc->id, 'role' => 'member']);
            Inside::ok('line', "チャネル {$channel->channel_id} の権限に「{$acc->name}」を追加しました");
        }
        return $this->back($channel, 'roles', '権限を追加しました');
    }
}
