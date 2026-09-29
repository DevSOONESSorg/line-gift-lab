<?php

namespace App\Http\Controllers\Mock;

use App\Http\Controllers\Controller;
use App\Models\Mock\LiffApp;
use App\Models\Mock\LineUser;
use App\Models\Mock\Message;
use App\Models\Mock\OfficialAccount;
use App\Services\MockLine\MockLine;
use App\Support\Inside;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// 疑似スマホ（お客さん・オーナーのLINEアプリの役）
class PhoneController extends Controller
{
    // URL を「スマホの中のブラウザ」で開くときの行き先を決める
    //   https://liff.line.me/{LIFF_ID} → LIFF のエンドポイントURL に置き換える（本物のLINEと同じ動き）
    //   https://line.me/R/ti/p/@xxx   → 友だち追加
    private function resolveOpen(string $target, LineUser $me): array
    {
        $u = parse_url($target);
        if (! $u) return ['error' => "URLとして読めません: {$target}"];
        $host = $u['host'] ?? 'localhost';

        if ($host === 'line.me' && str_starts_with($u['path'] ?? '', '/R/ti/p/')) {
            return ['add' => urldecode(substr($u['path'], 8))];
        }

        $liff = null;
        if ($host === 'liff.line.me') {
            $parts = array_values(array_filter(explode('/', $u['path'] ?? '')));
            $liffId = array_shift($parts);
            $liff = LiffApp::find($liffId);
            if (! $liff) {
                Inside::ng('line', "LIFF ID「{$liffId}」は見つかりません", 'リッチメニューやQRに入れた LIFF ID が間違っていないか確認しましょう');
                return ['error' => "LIFF ID「{$liffId}」のLIFFアプリはありません。LIFF IDの打ち間違いかもしれません。"];
            }
            $ep = parse_url($liff->endpoint_url);
            $path = rtrim($ep['path'] ?? '', '/').($parts ? '/'.implode('/', $parts) : '');
            parse_str($ep['query'] ?? '', $q1);
            parse_str($u['query'] ?? '', $q2);
            $u = ['host' => $ep['host'] ?? 'localhost', 'path' => $path ?: '/', 'query' => http_build_query(array_merge($q1, $q2))];
            $host = $u['host'];
            Inside::info('line', "LIFF {$liffId} を開きます → エンドポイントURL {$liff->endpoint_url}", 'LINEは「LIFF ID → エンドポイントURL」の対応表を見て、そのページをLINEの中で開きます');
        }

        $local = in_array($host, ['localhost', '127.0.0.1'], true) || ! isset($u['host']);
        if (! $local && ! $liff) return ['external' => $target];

        parse_str($u['query'] ?? '', $q);
        if ($liff) { $q['mock_uid'] = $me->user_id; $q['mock_liff'] = $liff->liff_id; }
        $src = ($u['path'] ?? '/').($q ? '?'.http_build_query($q) : '');
        return ['iframe' => $local ? $src : $target, 'liffId' => $liff?->liff_id];
    }

    public function index(Request $request)
    {
        $me = LineUser::me();
        $friends = OfficialAccount::join('friends', 'friends.official_account_id', '=', 'official_accounts.id')
            ->where('friends.user_id', $me->user_id)->orderBy('official_accounts.id')->get(['official_accounts.*', 'friends.blocked']);
        $chat = $request->query('chat') ? OfficialAccount::find($request->query('chat')) : null;
        $messages = collect();
        $richMenu = null;
        $friendRow = null;
        if ($chat) {
            $friendRow = DB::connection('mockline')->table('friends')->where(['official_account_id' => $chat->id, 'user_id' => $me->user_id])->first();
            $messages = Message::where(['official_account_id' => $chat->id, 'user_id' => $me->user_id])->orderBy('id')->get();
            $richMenu = $chat->richMenus()->where('is_default', true)->latest('id')->first();
        }
        $open = null;
        $addTarget = $request->query('add');
        if ($request->query('open')) {
            $open = $this->resolveOpen($request->query('open'), $me);
            if (isset($open['add'])) { $addTarget = $open['add']; $open = null; }
        }
        $addOa = $addTarget ? (OfficialAccount::byBasicId($addTarget) ?? (object) ['notFound' => $addTarget]) : null;

        return view('mock.phone', compact('me', 'friends', 'chat', 'messages', 'richMenu', 'friendRow', 'open', 'addOa') + [
            'lastId' => $messages->last()?->id ?? 0,
        ]);
    }

    public function rename(Request $request)
    {
        LineUser::query()->update(['display_name' => mb_substr(trim((string) $request->input('display_name')) ?: 'あなた', 0, 20)]);
        return redirect()->route('mock.phone');
    }

    public function add(Request $request)
    {
        $oa = OfficialAccount::byBasicId($request->input('basic_id'));
        if (! $oa) return redirect()->route('mock.phone', ['add' => $request->input('basic_id')]);
        MockLine::follow($oa, LineUser::me());
        return redirect()->route('mock.phone', ['chat' => $oa->id]);
    }

    public function send(Request $request, OfficialAccount $oa)
    {
        $me = LineUser::me();
        $text = trim((string) $request->input('text'));
        if ($text !== '' && $oa->isFriend($me->user_id)) MockLine::userSendsText($oa, $me, $text);
        return redirect()->route('mock.phone', ['chat' => $oa->id]);
    }

    public function block(Request $request, OfficialAccount $oa)
    {
        MockLine::block($oa, LineUser::me(), $request->boolean('blocked'));
        return redirect()->route('mock.phone', ['chat' => $oa->id]);
    }

    public function last(OfficialAccount $oa)
    {
        return ['lastId' => (int) Message::where(['official_account_id' => $oa->id, 'user_id' => LineUser::me()->user_id])->max('id')];
    }

    // LIFF の liff.sendMessages() の代わり：LIFF から「お客さんとして」トークにメッセージを送る
    // （本物では LIFF の scope に chat_message.write が必要）
    public function liffSend(Request $request)
    {
        $liff = LiffApp::find($request->input('liff_id'));
        $oa = OfficialAccount::byBasicId($request->input('basic_id'));
        if (! $liff || ! $oa) return response()->json(['ok' => false, 'message' => 'LIFF または公式アカウントが見つかりません'], 404);
        if (! str_contains($liff->scopes, 'chat_message.write')) {
            Inside::ng('line', "LIFF {$liff->liff_id} からの送信を断りました（scope に chat_message.write がない）");
            return response()->json(['ok' => false, 'message' => 'scope に chat_message.write がありません'], 403);
        }
        MockLine::userSendsText($oa, LineUser::me(), mb_substr((string) $request->input('text'), 0, 200), 'liff');
        return ['ok' => true];
    }
}
