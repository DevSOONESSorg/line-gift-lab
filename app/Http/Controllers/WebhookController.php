<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Store;
use App\Services\Bots\PlatformBot;
use App\Services\Bots\StoreBot;
use App\Services\Line\Signature;
use App\Support\Inside;
use Illuminate\Http\Request;

// =====================================================
// Webhook の受け口（LINE社 → 自社サーバー）
//   1. URL の slug から「どの店舗宛てか」を決める
//   2. 署名（X-Line-Signature）を、その店舗のチャネルシークレットで確かめる
//   3. 200 を返す（＝受け取りました）
//   4. イベントを bot に渡す
// =====================================================
class WebhookController extends Controller
{
    public function store(Request $request, string $slug)
    {
        $raw = $request->getContent();                       // 署名の確認には「届いたままの本文」が必要
        $signature = $request->header('X-Line-Signature');
        $store = Store::where('slug', $slug)->first();

        if (! $store) {
            Inside::ng('app', "Webhook受信 → 404「{$slug}」という slug の店舗はありません", '管理画面の slug と、Developers に登録した Webhook URL の slug が同じか確認しましょう');
            return response()->json(['message' => 'store not found'], 404);
        }
        $secret = $store->line_messaging_channel_secret;
        if (! $secret) {
            Inside::ng('app', "Webhook受信 → 500「{$store->name}」のチャネルシークレットが未設定です", '管理画面の LINE 連携設定に入れましょう');
            return response()->json(['message' => 'channel secret not set'], 500);
        }
        if (! Signature::verify($secret, $raw, $signature)) {
            Inside::ng('app', "Webhook受信 → 401 署名が合いません（「{$store->name}」宛て）",
                "届いた署名:   {$signature}\n自分で計算:   ".Signature::sign($secret, $raw)."\n（管理画面のシークレット ".Inside::mask($secret)." で計算）\n\n→ LINE社が使ったシークレットと、管理画面に入れたシークレットが違います");
            return response()->json(['message' => 'invalid signature'], 401);
        }

        $events = json_decode($raw, true)['events'] ?? [];
        Inside::ok('app', "Webhook受信 → 200 署名OK（「{$store->name}」宛て）: ".self::describe($events),
            "slug「{$store->slug}」→ 店舗「{$store->name}」と判断\nシークレットで計算した署名が一致 → 本物のLINE社からの通知と確認");

        // 本来は「先に200を返して、処理は後で（キュー）」が定石。教材では分かりやすさのためその場で処理
        foreach ($events as $event) {
            try { StoreBot::handle($store, $event); } catch (\Throwable $e) { Inside::ng('app', 'bot の処理中にエラー', $e->getMessage()); }
        }
        return response()->json([]);
    }

    public function platform(Request $request)
    {
        $raw = $request->getContent();
        if (! Signature::verify(Setting::get('platform_secret'), $raw, $request->header('X-Line-Signature'))) {
            Inside::ng('app', 'Webhook受信（共通チャネル）→ 401 署名が合いません');
            return response()->json(['message' => 'invalid signature'], 401);
        }
        $events = json_decode($raw, true)['events'] ?? [];
        Inside::ok('app', 'Webhook受信（共通チャネル）→ 200 署名OK: '.self::describe($events));
        foreach ($events as $event) {
            try { PlatformBot::handle($event); } catch (\Throwable $e) { Inside::ng('app', 'bot の処理中にエラー', $e->getMessage()); }
        }
        return response()->json([]);
    }

    private static function describe(array $events): string
    {
        if (! $events) return '中身なし（Verify＝接続確認）';
        return implode(', ', array_map(fn ($e) => $e['type'] === 'message' ? 'message「'.($e['message']['text'] ?? $e['message']['type']).'」' : $e['type'], $events));
    }
}
