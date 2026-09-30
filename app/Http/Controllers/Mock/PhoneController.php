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

// 疑似スマホ（LINEアプリの役）。お客さんのスマホと、オーナーのスマホの2台がある
//   {phone} には customer（お客さん）か owner（オーナー）が入る
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
        // スマホの中のブラウザの上部に出す「アプリ名」と「ドメイン」、LIFF のサイズ（Full / Tall / Compact）
        $ep = $liff ? parse_url($liff->endpoint_url) : $u;
        return ['iframe' => $local ? $src : $target, 'liffId' => $liff?->liff_id, 'liffName' => $liff?->name, 'size' => $liff?->size ?? 'Full',
            'host' => ($ep['host'] ?? 'localhost').(isset($ep['port']) ? ':'.$ep['port'] : '')];
    }

    // 2台を左右に並べる画面。?add= や ?open= が付いていたら、?to= のスマホ（省略時はお客さん）で開く
    public function both(Request $request)
    {
        $to = in_array($request->query('to'), ['customer', 'owner'], true) ? $request->query('to') : 'customer';
        $pass = array_filter($request->only(['add', 'open', 'chat']));
        $src = [];
        foreach (array_keys(LineUser::PHONES) as $phone) {
            $src[$phone] = route('mock.phone.screen', ['phone' => $phone] + ($phone === $to ? $pass : []));
        }
        return view('mock.phones', ['src' => $src, 'users' => collect(LineUser::PHONES)->map(fn ($l, $p) => LineUser::me($p))]);
    }

    // スマホ1台の画面（左右の枠の中に表示される）
    public function index(Request $request, string $phone)
    {
        $me = LineUser::me($phone);
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

        return view('mock.phone', compact('phone', 'me', 'friends', 'chat', 'messages', 'richMenu', 'friendRow', 'open', 'addOa') + [
            'lastId' => $messages->last()?->id ?? 0,
        ]);
    }

    public function rename(Request $request, string $phone)
    {
        LineUser::me($phone)->update(['display_name' => mb_substr(trim((string) $request->input('display_name')) ?: LineUser::PHONES[$phone], 0, 20)]);
        return redirect()->route('mock.phone.screen', $phone);
    }

    public function add(Request $request, string $phone)
    {
        $oa = OfficialAccount::byBasicId($request->input('basic_id'));
        if (! $oa) return redirect()->route('mock.phone.screen', ['phone' => $phone, 'add' => $request->input('basic_id')]);
        MockLine::follow($oa, LineUser::me($phone));
        return redirect()->route('mock.phone.screen', ['phone' => $phone, 'chat' => $oa->id]);
    }

    public function send(Request $request, string $phone, OfficialAccount $oa)
    {
        $me = LineUser::me($phone);
        $text = trim((string) $request->input('text'));
        if ($text !== '' && $oa->isFriend($me->user_id)) MockLine::userSendsText($oa, $me, $text);
        return redirect()->route('mock.phone.screen', ['phone' => $phone, 'chat' => $oa->id]);
    }

    public function block(Request $request, string $phone, OfficialAccount $oa)
    {
        MockLine::block($oa, LineUser::me($phone), $request->boolean('blocked'));
        return redirect()->route('mock.phone.screen', ['phone' => $phone, 'chat' => $oa->id]);
    }

    public function last(string $phone, OfficialAccount $oa)
    {
        return ['lastId' => (int) Message::where(['official_account_id' => $oa->id, 'user_id' => LineUser::me($phone)->user_id])->max('id')];
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
        // どちらのスマホの LIFF から送ったかは、画面から一緒に送られてくる mock_uid で決める
        $me = LineUser::find((string) $request->input('mock_uid')) ?? LineUser::me();
        MockLine::userSendsText($oa, $me, mb_substr((string) $request->input('text'), 0, 200), 'liff');
        return ['ok' => true];
    }
}
