<?php

namespace App\Http\Controllers\Mock;

use App\Http\Controllers\Controller;
use App\Models\Mock\Account;
use App\Models\Mock\OfficialAccount;
use App\Models\Mock\Provider;
use App\Services\MockLine\MockLine;
use App\Support\Inside;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// 疑似 LINE Official Account Manager（manager.line.biz の代わり）
class ManagerController extends Controller
{
    public const ROLES = ['admin' => '管理者', 'operator' => '運用担当者'];

    private function me(Request $r): string { return $r->session()->get('mock_account', 'personal'); }

    // その公式アカウントのメンバーか確認
    private function guard(Request $r, OfficialAccount $oa): string
    {
        $role = $oa->roleOf($this->me($r));
        abort_unless($role, 403, "「".Account::find($this->me($r))->name."」は「{$oa->name}」のメンバーではありません。権限管理で招待URLを発行してもらい、参加してください。");
        view()->share(['oa' => $oa, 'role' => $role, 'roles' => self::ROLES]);
        return $role;
    }

    public function index(Request $r)
    {
        return view('mock.manager.index', ['oas' => OfficialAccount::of($this->me($r)), 'roles' => self::ROLES]);
    }

    public function create() { return view('mock.manager.create'); }

    public function store(Request $r)
    {
        $data = $r->validate(['name' => 'required|max:50', 'country' => 'required', 'agree' => 'accepted'], [], ['name' => 'アカウント名', 'country' => '所在国', 'agree' => '利用規約への同意']);
        $oa = MockLine::createOfficialAccount($data['name'], $this->me($r), (string) $r->input('industry'));
        return redirect()->route('mock.manager.oa.home', $oa)->with('msg', "「{$oa->name}」を作成しました。あなたが管理者です。");
    }

    public function home(Request $r, OfficialAccount $oa)
    {
        $this->guard($r, $oa);
        $friends = DB::connection('mockline')->table('friends')->where(['official_account_id' => $oa->id, 'blocked' => false])->count();
        return view('mock.manager.home', ['friends' => $friends, 'channel' => $oa->messagingChannel]);
    }

    public function settings(Request $r, OfficialAccount $oa) { $this->guard($r, $oa); return view('mock.manager.settings'); }

    public function members(Request $r, OfficialAccount $oa)
    {
        $this->guard($r, $oa);
        $members = DB::connection('mockline')->table('oa_members')->join('accounts', 'accounts.id', '=', 'oa_members.account_id')
            ->where('official_account_id', $oa->id)->get();
        $invite = $r->query('invite') ? DB::connection('mockline')->table('invites')->where('token', $r->query('invite'))->first() : null;
        return view('mock.manager.members', compact('members', 'invite'));
    }

    public function createInvite(Request $r, OfficialAccount $oa)
    {
        abort_unless($this->guard($r, $oa) === 'admin', 403, 'メンバーを追加できるのは管理者だけです。');
        $role = $r->input('role') === 'admin' ? 'admin' : 'operator';
        $token = Str::random(40);
        DB::connection('mockline')->table('invites')->insert(['token' => $token, 'official_account_id' => $oa->id, 'role' => $role, 'expires_at' => now()->addDay()]);
        Inside::info('line', "「{$oa->name}」の招待URLを発行しました（権限: ".self::ROLES[$role].'）', '24時間有効・1回だけ使えます');
        return redirect()->route('mock.manager.oa.members', [$oa, 'invite' => $token]);
    }

    public function invite(Request $r, string $token)
    {
        $inv = DB::connection('mockline')->table('invites')->where('token', $token)->first();
        $error = ! $inv ? 'この招待URLは存在しません。'
            : ($inv->used_by ? 'この招待URLはすでに使われています（1回だけ有効）。もう一度発行してもらってください。'
            : (now()->gt($inv->expires_at) ? 'この招待URLは有効期限（24時間）が切れています。' : ''));
        $oa = $inv ? OfficialAccount::find($inv->official_account_id) : null;
        return view('mock.manager.invite', ['inv' => $inv, 'invOa' => $oa, 'error' => $error, 'roles' => self::ROLES,
            'already' => $oa?->roleOf($this->me($r))]);
    }

    public function join(Request $r, string $token)
    {
        $inv = DB::connection('mockline')->table('invites')->where('token', $token)->first();
        if (! $inv || $inv->used_by || now()->gt($inv->expires_at)) return redirect()->route('mock.manager.invite', $token);
        $me = $this->me($r);
        $oa = OfficialAccount::find($inv->official_account_id);
        if (! $oa->roleOf($me)) DB::connection('mockline')->table('oa_members')->insert(['official_account_id' => $oa->id, 'account_id' => $me, 'role' => $inv->role]);
        DB::connection('mockline')->table('invites')->where('token', $token)->update(['used_by' => $me]);
        Inside::ok('line', '「'.Account::find($me)->name."」が「{$oa->name}」に".self::ROLES[$inv->role].'として参加しました');
        return redirect()->route('mock.manager.oa.home', $oa)->with('msg', '参加しました');
    }

    public function messaging(Request $r, OfficialAccount $oa)
    {
        $this->guard($r, $oa);
        $ch = $oa->messagingChannel;
        return view('mock.manager.messaging', ['channel' => $ch, 'provider' => $ch?->provider,
            'providers' => Provider::of($this->me($r)), 'hasRole' => $ch && $ch->roleOf($this->me($r))]);
    }

    public function enableMessaging(Request $r, OfficialAccount $oa)
    {
        $this->guard($r, $oa);
        $me = $this->me($r);
        $pid = $r->input('provider_id') === 'new' ? null : (int) $r->input('provider_id');
        $newName = trim((string) $r->input('new_provider'));
        if (! $pid && ! $newName) return back()->with('msg', 'プロバイダーを選んでください');
        abort_if($pid && ! Provider::find($pid)?->hasMember($me), 403, 'そのプロバイダーのメンバーではありません。');
        MockLine::enableMessagingApi($oa, $pid, $me, $newName ?: null);
        return redirect()->route('mock.manager.oa.messaging', $oa)->with('msg', 'Messaging APIを有効にしました');
    }

    public function response(Request $r, OfficialAccount $oa)
    {
        $this->guard($r, $oa);
        return view('mock.manager.response', ['channel' => $oa->messagingChannel]);
    }

    public function saveResponse(Request $r, OfficialAccount $oa)
    {
        $this->guard($r, $oa);
        $oa->update([
            'response_mode' => $r->input('response_mode') === 'chat' ? 'chat' : 'bot',
            'auto_reply_on' => $r->input('auto_reply_on') === '1', 'auto_reply_text' => (string) $r->input('auto_reply_text'),
            'greeting_on' => $r->input('greeting_on') === '1', 'greeting_text' => (string) $r->input('greeting_text'),
        ]);
        // Manager の「Webhook」と Developers の「Use webhook」は同じ1つのスイッチ
        if ($oa->messagingChannel && $r->has('webhook')) $oa->messagingChannel->update(['use_webhook' => $r->input('webhook') === '1']);
        Inside::info('line', "「{$oa->name}」の応答設定を変更しました",
            '応答モード: '.($oa->response_mode === 'bot' ? 'Bot' : 'チャット')."\n応答メッセージ: ".($oa->auto_reply_on ? 'ON' : 'OFF')
            ."\nWebhook: ".($oa->messagingChannel ? ($oa->messagingChannel->fresh()->use_webhook ? 'ON' : 'OFF') : '（Messaging API未設定）'));
        return redirect()->route('mock.manager.oa.response', $oa)->with('msg', '保存しました');
    }
}
