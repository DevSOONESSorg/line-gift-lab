<?php

namespace App\Http\Controllers\Mock;

use App\Http\Controllers\Controller;
use App\Models\Mock\Account;
use App\Models\Mock\Channel;
use App\Models\Mock\LiffApp;
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
        if (! $r->filled('name') || $r->input('app_type') !== 'web') {
            return redirect()->route('mock.developers.provider', [$provider, 'create' => 'login'])->with('msg', 'チャネル名を入れ、アプリタイプで「ウェブアプリ」を選んでください。');
        }
        $ch = MockLine::createLoginChannel($provider->id, $r->input('name'), $this->me($r), (string) $r->input('description'));
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
        return view('mock.developers.channel', ['tab' => $r->query('tab', 'basic'), 'liffs' => $channel->liffApps, 'roles' => $roles,
            'accounts' => Account::all(), 'verify' => session('verify')]);
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
        $channel->update(['webhook_url' => trim((string) $r->input('webhook_url'))]);
        Inside::info('line', "Webhook URLを登録しました（{$channel->channel_id}）", $channel->webhook_url ?: '(空)');
        return $this->back($channel, 'messaging', 'Webhook URLを更新しました');
    }

    public function verify(Request $r, Channel $channel)
    {
        $this->guard($r, $channel);
        $result = MockLine::sendWebhook($channel, [], true);
        return $this->back($channel, 'messaging')->with('verify', $result);
    }

    public function toggleWebhook(Request $r, Channel $channel)
    {
        $this->guard($r, $channel);
        $channel->update(['use_webhook' => ! $channel->use_webhook]);
        Inside::info('line', 'Use webhook を '.($channel->use_webhook ? 'ON' : 'OFF')." にしました（{$channel->channel_id}）");
        return $this->back($channel, 'messaging');
    }

    public function publish(Request $r, Channel $channel)
    {
        $this->guard($r, $channel);
        $channel->update(['is_published' => ! $channel->is_published]);
        Inside::info('line', "LINEログインチャネル {$channel->channel_id} を「".($channel->is_published ? '公開' : '開発中')."」にしました",
            '本物では「開発中」のあいだ、チャネルの管理者・テスター以外は LIFF を開けません（本番の店舗オンボーディングで忘れやすいポイント）');
        return $this->back($channel, 'basic');
    }

    public function addLiff(Request $r, Channel $channel)
    {
        $this->guard($r, $channel);
        if ($channel->type !== 'login') return $this->back($channel, 'basic', 'LIFFはLINEログインチャネルに追加します');
        $endpoint = trim((string) $r->input('endpoint_url'));
        if (! preg_match('#^https?://#', $endpoint)) return $this->back($channel, 'liff', 'エンドポイントURLは http:// か https:// で始まるURLを入れてください');
        $liff = MockLine::addLiff($channel, $r->input('name') ?: $channel->name, $endpoint, $r->input('size', 'Full'),
            implode(' ', (array) $r->input('scopes', ['profile'])), $r->boolean('bot_prompt'));
        return $this->back($channel, 'liff', "LIFFアプリを追加しました。LIFF ID: {$liff->liff_id}");
    }

    public function updateLiff(Request $r, Channel $channel, string $liffId)
    {
        $this->guard($r, $channel);
        LiffApp::where(['liff_id' => $liffId, 'channel_id' => $channel->id])->update(['endpoint_url' => trim((string) $r->input('endpoint_url'))]);
        Inside::info('line', "LIFF {$liffId} のエンドポイントURLを変更しました", $r->input('endpoint_url'));
        return $this->back($channel, 'liff', 'エンドポイントURLを更新しました');
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
