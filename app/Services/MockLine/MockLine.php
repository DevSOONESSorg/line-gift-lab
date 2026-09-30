<?php

namespace App\Services\MockLine;

use App\Models\Mock\Channel;
use App\Models\Mock\LiffApp;
use App\Models\Mock\LineUser;
use App\Models\Mock\Message;
use App\Models\Mock\OfficialAccount;
use App\Models\Mock\Provider;
use App\Services\Line\Signature;
use App\Support\Inside;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

// =====================================================
// 疑似LINE（LINE社の役）の中身
// 本物では LINE社のサーバーの中にあって見えない部分を、教材として自分たちで作っています。
// =====================================================
class MockLine
{
    public const PLATFORM_PROVIDER = 'おくりギフト運営プロバイダー（株式会社サンプル）';

    private static function db() { return DB::connection('mockline'); }

    private static function accountName(string $id): string
    {
        return self::db()->table('accounts')->where('id', $id)->value('name') ?? $id;
    }

    // ---------- 公式アカウント・チャネルを作る ----------

    public static function createOfficialAccount(string $name, string $owner, string $industry = '', bool $isPlatform = false): OfficialAccount
    {
        do {
            $basic = '@'.random_int(100, 999).implode('', array_map(fn () => chr(random_int(97, 122)), range(1, 5)));
        } while (OfficialAccount::byBasicId($basic));
        $oa = OfficialAccount::create([
            'name' => $name, 'basic_id' => $basic, 'bot_user_id' => 'U'.bin2hex(random_bytes(16)), 'industry' => $industry,
            'auto_reply_text' => config('lab.default_auto_reply'),
            'greeting_text' => config('lab.default_greeting'),
            'is_platform' => $isPlatform,
        ]);
        self::db()->table('oa_members')->insert(['official_account_id' => $oa->id, 'account_id' => $owner, 'role' => 'admin']);
        Inside::ok('line', "公式アカウント「{$name}」を作成しました（{$basic}）", '作成したアカウント: '.self::accountName($owner)."\n→ この人が「管理者」になります");
        return $oa;
    }

    public static function newChannelId(): string
    {
        do { $cid = '2'.random_int(100000000, 999999999); } while (Channel::byChannelId($cid));
        return $cid;
    }

    // Manager の「Messaging APIを利用する」
    public static function enableMessagingApi(OfficialAccount $oa, ?int $providerId, string $account, ?string $newProviderName = null): Channel
    {
        if ($oa->messagingChannel) return $oa->messagingChannel;
        if (! $providerId && $newProviderName) {
            $providerId = Provider::create(['name' => $newProviderName])->id;
            self::db()->table('provider_members')->insert(['provider_id' => $providerId, 'account_id' => $account, 'role' => 'admin']);
        }
        $ch = Channel::create([
            'channel_id' => self::newChannelId(), 'type' => 'messaging', 'name' => $oa->name,
            'provider_id' => $providerId, 'official_account_id' => $oa->id, 'secret' => bin2hex(random_bytes(16)), 'created_by' => $account,
        ]);
        // チャネルの権限は「有効化した人」だけに付く
        self::db()->table('channel_roles')->insert(['channel_id' => $ch->id, 'account_id' => $account, 'role' => 'admin']);
        Inside::ok('line', "Messaging APIチャネルを作成しました（{$ch->channel_id}）",
            'プロバイダー: '.Provider::find($providerId)->name."\n作成者（チャネルの権限を持つ人）: ".self::accountName($account));
        return $ch;
    }

    public static function createLoginChannel(int $providerId, string $name, string $account, string $description = ''): Channel
    {
        $ch = Channel::create([
            'channel_id' => self::newChannelId(), 'type' => 'login', 'name' => $name, 'description' => $description,
            'provider_id' => $providerId, 'secret' => bin2hex(random_bytes(16)), 'created_by' => $account,
        ]);
        self::db()->table('channel_roles')->insert(['channel_id' => $ch->id, 'account_id' => $account, 'role' => 'admin']);
        Inside::ok('line', "LINEログインチャネルを作成しました（{$ch->channel_id}）", "チャネル名: {$name}\n※ 作った直後は「開発中」。一般の人が LIFF を開くには「公開」が必要");
        return $ch;
    }

    public static function addLiff(Channel $channel, string $name, string $endpoint, string $size = 'Full', string $scopes = 'profile openid', bool $botPrompt = false): LiffApp
    {
        $liffId = $channel->channel_id.'-'.Str::ucfirst(Str::random(8));
        $liff = LiffApp::create(['liff_id' => $liffId, 'channel_id' => $channel->id, 'name' => $name, 'size' => $size,
            'endpoint_url' => $endpoint, 'scopes' => $scopes, 'bot_prompt' => $botPrompt]);
        Inside::ok('line', "LIFFアプリを追加しました（LIFF ID: {$liffId}）", "エンドポイントURL: {$endpoint}\n→ https://liff.line.me/{$liffId} を開くと、このURLのページがLINEの中で開きます");
        return $liff;
    }

    public static function issueToken(Channel $channel): string
    {
        $token = bin2hex(random_bytes(8)).'/'.rtrim(base64_encode(random_bytes(48)), '=').'=';
        $channel->update(['access_token' => $token]);
        Inside::ok('line', "長期チャネルアクセストークンを発行しました（{$channel->channel_id}）", '以前のトークンがあれば、それはもう使えません（再発行＝古い鍵は無効）');
        return $token;
    }

    public static function reissueSecret(Channel $channel): string
    {
        $channel->update(['secret' => bin2hex(random_bytes(16))]);
        Inside::info('line', "チャネルシークレットを再発行しました（{$channel->channel_id}）", '古いシークレットで署名を確かめているサーバーは、Webhookを受け取れなくなります');
        return $channel->secret;
    }

    // ---------- メッセージのやりとり ----------

    // あいさつメッセージ。本物の Manager と同じく {Nickname}（友だちの表示名）と {AccountName}（アカウント名）が使える
    public static function greet(OfficialAccount $oa, LineUser $user): void
    {
        self::addMessage($oa->id, $user->user_id, 'out', 'greeting', strtr($oa->greeting_text, ['{Nickname}' => $user->display_name, '{AccountName}' => $oa->name]));
    }

    public static function addMessage(int $oaId, string $userId, string $direction, string $via, string $text): void
    {
        Message::create(['official_account_id' => $oaId, 'user_id' => $userId, 'direction' => $direction, 'via' => $via, 'text' => $text]);
    }

    public static function newReplyToken(Channel $ch, string $userId): string
    {
        $token = bin2hex(random_bytes(16));
        self::db()->table('reply_tokens')->insert(['token' => $token, 'channel_id' => $ch->id, 'user_id' => $userId, 'expires_at' => now()->addMinute(), 'used' => false]);
        return $token;
    }

    // Webhook の送り先を決める。localhost 宛てなら、コンテナの中の自分自身に送る
    public static function resolveWebhookTarget(string $url): ?string
    {
        $u = parse_url($url);
        if (! $u || empty($u['host'])) return null;
        if (in_array($u['host'], ['localhost', '127.0.0.1'], true)) {
            return 'http://127.0.0.1:'.env('APP_INTERNAL_PORT', 3000).($u['path'] ?? '/').(isset($u['query']) ? '?'.$u['query'] : '');
        }
        return $url;
    }

    // LINE社 → 自社サーバー へ Webhook を送る
    public static function sendWebhook(Channel $ch, array $events, bool $verify = false): array
    {
        $oa = $ch->officialAccount;
        $body = json_encode(['destination' => $oa?->bot_user_id ?? '', 'events' => $events], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = Signature::sign($ch->secret, $body);
        $target = self::resolveWebhookTarget($ch->webhook_url);
        $what = $verify ? '検証（Verify）' : implode(', ', array_column($events, 'type'));

        if (! $target) {
            Inside::ng('line', 'Webhookを送れません（URLが正しくありません）: '.($ch->webhook_url ?: '(空)'));
            return ['ok' => false, 'status' => 0, 'message' => 'Webhook URL が正しくありません'];
        }
        try {
            $res = Http::timeout(8)->withHeaders(['X-Line-Signature' => $signature, 'User-Agent' => 'LineBotWebhook/2.0 (mock)'])
                ->withBody($body, 'application/json')->post($target);
            $detail = "送信先: {$ch->webhook_url}\nX-Line-Signature: {$signature}\n（チャネルシークレット ".Inside::mask($ch->secret)." で計算）\n\n本文:\n"
                .json_encode(json_decode($body), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                ."\n\n返ってきた答え: {$res->status()} ".mb_substr($res->body(), 0, 200);
            $res->status() === 200 ? Inside::ok('line', "Webhook送信 [{$what}] → 200 OK", $detail) : Inside::ng('line', "Webhook送信 [{$what}] → {$res->status()}", $detail);
            return ['ok' => $res->status() === 200, 'status' => $res->status(), 'message' => mb_substr($res->body(), 0, 200)];
        } catch (\Throwable $e) {
            Inside::ng('line', "Webhook送信 [{$what}] → つながりませんでした", "送信先: {$ch->webhook_url}\nエラー: ".$e->getMessage());
            return ['ok' => false, 'status' => 0, 'message' => $e->getMessage()];
        }
    }

    private static function event(string $type, string $userId, array $extra = []): array
    {
        return array_merge([
            'type' => $type, 'mode' => 'active', 'timestamp' => (int) (microtime(true) * 1000),
            'source' => ['type' => 'user', 'userId' => $userId],
            'webhookEventId' => strtoupper(bin2hex(random_bytes(13))), 'deliveryContext' => ['isRedelivery' => false],
        ], $extra);
    }

    // Webhook を送る条件がそろっているか。そろっていなければ理由を返す
    public static function webhookReady(OfficialAccount $oa): array
    {
        $ch = $oa->messagingChannel;
        if (! $ch) return [null, 'Messaging API が有効になっていない'];
        if ($oa->response_mode !== 'bot') return [$ch, '応答モードが「チャット」になっている'];
        if (! $ch->use_webhook) return [$ch, 'Webhook（Use webhook）がOFF'];
        if (! $ch->webhook_url) return [$ch, 'Webhook URL が空'];
        return [$ch, null];
    }

    public static function follow(OfficialAccount $oa, LineUser $user): void
    {
        $row = self::db()->table('friends')->where(['official_account_id' => $oa->id, 'user_id' => $user->user_id])->first();
        if ($row && ! $row->blocked) return;
        self::db()->table('friends')->updateOrInsert(['official_account_id' => $oa->id, 'user_id' => $user->user_id], ['blocked' => false]);
        Inside::info('phone', "「{$oa->name}」を友だち追加しました", "あなたのユーザーID: {$user->user_id}");
        if ($oa->greeting_on && ! $row) self::greet($oa, $user);

        [$ch, $why] = self::webhookReady($oa);
        if ($why) { Inside::info('line', "followイベントは自社サーバーに送りません（{$why}）"); return; }
        self::sendWebhook($ch, [self::event('follow', $user->user_id, ['replyToken' => self::newReplyToken($ch, $user->user_id)])]);
    }

    public static function block(OfficialAccount $oa, LineUser $user, bool $blocked): void
    {
        self::db()->table('friends')->where(['official_account_id' => $oa->id, 'user_id' => $user->user_id])->update(['blocked' => $blocked]);
        Inside::info('phone', "「{$oa->name}」を".($blocked ? 'ブロック' : 'ブロック解除').'しました');
        [$ch, $why] = self::webhookReady($oa);
        if (! $why) {
            self::sendWebhook($ch, [$blocked ? self::event('unfollow', $user->user_id)
                : self::event('follow', $user->user_id, ['replyToken' => self::newReplyToken($ch, $user->user_id)])]);
        }
    }

    // ユーザーがトークで文字を送った
    public static function userSendsText(OfficialAccount $oa, LineUser $user, string $text, string $via = 'user'): void
    {
        self::addMessage($oa->id, $user->user_id, 'in', $via, $text);
        Inside::info('phone', "「{$oa->name}」に「{$text}」と送信しました".($via === 'liff' ? '（LIFFから送信）' : ''));

        if ($oa->auto_reply_on && $oa->response_mode === 'bot') {
            self::addMessage($oa->id, $user->user_id, 'out', 'auto', $oa->auto_reply_text);
            Inside::info('line', '応答メッセージ（定型文）を自動で返しました', '応答メッセージがONなので、botの返事とは別にLINE社が返しています');
        }
        [$ch, $why] = self::webhookReady($oa);
        if ($why) { Inside::info('line', "メッセージを自社サーバーに送りません（{$why}）"); return; }
        self::sendWebhook($ch, [self::event('message', $user->user_id, [
            'replyToken' => self::newReplyToken($ch, $user->user_id),
            'message' => ['id' => (string) random_int(10 ** 17, 10 ** 18 - 1), 'type' => 'text', 'quoteToken' => bin2hex(random_bytes(10)), 'text' => $text],
        ])]);
    }
}
