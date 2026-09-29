<?php

namespace App\Http\Controllers\Mock;

use App\Http\Controllers\Controller;
use App\Models\Mock\Channel;
use App\Models\Mock\LineUser;
use App\Services\MockLine\MockLine;
use App\Support\Inside;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// 疑似LINE の Messaging API（本物の https://api.line.me の代わり）
class ApiController extends Controller
{
    private function texts(array $messages): array
    {
        return array_map(fn ($m) => ($m['type'] ?? '') === 'text' ? $m['text'] : '［'.($m['type'] ?? '?').' メッセージ（疑似LINEでは表示できません）］', $messages);
    }

    public function reply(Request $request)
    {
        $ch = $request->attributes->get('channel');
        $row = DB::connection('mockline')->table('reply_tokens')->where('token', (string) $request->input('replyToken'))->first();
        if (! $row || $row->channel_id != $ch->id || $row->used || now()->gt($row->expires_at)) {
            Inside::ng('line', 'API 返信 → 400 返信用トークン（replyToken）が無効です', 'replyToken は1回だけ・約1分以内しか使えません');
            return response()->json(['message' => 'Invalid reply token'], 400);
        }
        DB::connection('mockline')->table('reply_tokens')->where('token', $row->token)->update(['used' => true]);
        $texts = $this->texts($request->input('messages', []));
        foreach ($texts as $t) MockLine::addMessage($ch->official_account_id, $row->user_id, 'out', 'bot', $t);
        Inside::ok('line', "API 返信 → 200 「{$ch->officialAccount->name}」から返信を届けました", implode("\n---\n", $texts));
        return response()->json([]);
    }

    public function push(Request $request)
    {
        $ch = $request->attributes->get('channel');
        $to = (string) $request->input('to');
        if (! $ch->officialAccount->isFriend($to)) {
            // 本物も、友だちでない／ブロック中の人への送信はエラーにならず「届かない」だけ
            Inside::ng('line', "API 送信 → 200 でも届いていません（".Inside::mask($to, 6)." は「{$ch->officialAccount->name}」の友だちではない／ブロック中）");
            return response()->json(['sentMessages' => []]);
        }
        $texts = $this->texts($request->input('messages', []));
        foreach ($texts as $t) MockLine::addMessage($ch->official_account_id, $to, 'out', 'bot', $t);
        Inside::ok('line', "API 送信 → 200 「{$ch->officialAccount->name}」からメッセージを届けました", implode("\n---\n", $texts));
        return response()->json(['sentMessages' => [['id' => (string) random_int(10 ** 17, 10 ** 18 - 1)]]]);
    }

    public function profile(Request $request, string $userId)
    {
        $ch = $request->attributes->get('channel');
        $user = LineUser::find($userId);
        if (! $user || ! $ch->officialAccount?->isFriend($userId)) return response()->json(['message' => 'Not found'], 404);
        return response()->json(['userId' => $user->user_id, 'displayName' => $user->display_name, 'language' => 'ja']);
    }

    public function info(Request $request)
    {
        $oa = $request->attributes->get('channel')->officialAccount;
        if (! $oa) return response()->json(['message' => 'Not found'], 404);
        Inside::info('line', "API ボット情報 → 200（このトークンは「{$oa->name}」{$oa->basic_id} のもの）");
        return response()->json(['userId' => $oa->bot_user_id, 'basicId' => $oa->basic_id, 'displayName' => $oa->name, 'chatMode' => $oa->response_mode]);
    }

    public function verifyToken(Request $request)
    {
        $ch = Channel::byToken((string) $request->input('access_token'));
        if (! $ch) return response()->json(['error' => 'invalid_request', 'error_description' => 'access token expired'], 400);
        Inside::info('line', "API トークン検証 → 200（このトークンはチャネル {$ch->channel_id} のもの）");
        return response()->json(['client_id' => $ch->channel_id, 'expires_in' => 2592000, 'scope' => '']);
    }
}
