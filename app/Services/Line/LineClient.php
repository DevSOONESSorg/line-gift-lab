<?php

namespace App\Services\Line;

use App\Models\Mock\Channel;
use App\Models\Setting;
use App\Models\Store;
use App\Support\Inside;
use Illuminate\Support\Facades\Http;

// =====================================================
// 自社サーバー → LINE社 へのお願い（Messaging API）
//
// どのお願いにも「チャネルアクセストークン」を付ける（＝話しかける許可証）。
// 送り先は、チャネルIDかトークンが疑似LINEのものなら疑似LINE、そうでなければ本物の api.line.me
// =====================================================
class LineClient
{
    public function __construct(public string $channelId, public string $token, public string $label = '') {}

    // 店舗専用チャネル
    public static function forStore(Store $store): self
    {
        return new self((string) $store->line_messaging_channel_id, (string) $store->line_messaging_channel_access_token, "店舗「{$store->name}」");
    }

    // 共通チャネル（運営の公式LINE）
    public static function platform(): self
    {
        return new self(Setting::get('platform_channel_id'), Setting::get('platform_token'), '運営（共通チャネル）');
    }

    public function base(): string
    {
        $isMock = Channel::byChannelId($this->channelId) || Channel::byToken($this->token);
        return $isMock ? 'http://127.0.0.1:'.env('APP_INTERNAL_PORT', 3000).'/mock-line-api' : 'https://api.line.me';
    }

    public function call(string $method, string $path, ?array $body = null): array
    {
        $base = $this->base();
        $where = str_contains($base, 'mock') ? '疑似LINE' : '本物のLINE';
        try {
            $req = Http::timeout(8)->withToken($this->token)->acceptJson();
            $res = $body === null ? $req->send($method, $base.$path) : $req->send($method, $base.$path, ['json' => $body]);
            $data = $res->json() ?? [];
            $detail = "送り先: {$where} {$method} {$path}\n使った許可証: {$this->label} のトークン ".Inside::mask($this->token, 6)
                .($body ? "\n\n送った内容:\n".json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '')
                ."\n\n答え: {$res->status()} ".json_encode($data, JSON_UNESCAPED_UNICODE);
            $res->successful() ? Inside::ok('app', "LINEにお願い {$method} {$path} → {$res->status()}", $detail)
                : Inside::ng('app', "LINEにお願い {$method} {$path} → {$res->status()}", $detail);
            return ['ok' => $res->successful(), 'status' => $res->status(), 'data' => $data];
        } catch (\Throwable $e) {
            Inside::ng('app', "LINEにお願い {$method} {$path} → つながりませんでした", $e->getMessage());
            return ['ok' => false, 'status' => 0, 'data' => ['message' => $e->getMessage()]];
        }
    }

    private static function texts(array|string $texts): array
    {
        return array_map(fn ($t) => ['type' => 'text', 'text' => $t], (array) $texts);
    }

    public function reply(string $replyToken, array|string $texts): array { return $this->call('POST', '/v2/bot/message/reply', ['replyToken' => $replyToken, 'messages' => self::texts($texts)]); }
    public function push(string $to, array|string $texts): array { return $this->call('POST', '/v2/bot/message/push', ['to' => $to, 'messages' => self::texts($texts)]); }
    public function profile(string $userId): array { return $this->call('GET', "/v2/bot/profile/{$userId}"); }
    public function botInfo(): array { return $this->call('GET', '/v2/bot/info'); }

    // このトークンはどのチャネルのもの？（トークンは本文で渡す）
    public function verifyToken(): array
    {
        try {
            $res = Http::timeout(8)->asForm()->post($this->base().'/v2/oauth/verify', ['access_token' => $this->token]);
            return ['ok' => $res->successful(), 'status' => $res->status(), 'data' => $res->json() ?? []];
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => 0, 'data' => ['message' => $e->getMessage()]];
        }
    }
}
