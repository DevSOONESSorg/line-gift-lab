<?php

namespace App\Services\Bots;

use App\Models\Store;
use App\Services\Line\LineClient;

// =====================================================
// 店舗専用の公式LINE の bot（コースBで書きかえる場所）
//
// 本番環境では「テキスト受信時にギフト誘導を自動返信する」が管理画面でON/OFFできます。
// ONなら、どんなメッセージにもギフトのリンクを返す。OFFなら何もしない（通常の問い合わせにはお店が手で返事）
// =====================================================
class StoreBot
{
    public static function handle(Store $store, array $event): void
    {
        $line = LineClient::forStore($store);
        $giftUrl = $store->liff_id ? "https://liff.line.me/{$store->liff_id}" : route('liff.store', $store->slug);

        if ($event['type'] === 'follow') {
            $line->reply($event['replyToken'], "{$store->name} の公式LINEです。\n下のメニューの「贈る」から、お店にギフトを贈れます。");
            return;
        }

        if ($event['type'] === 'message' && ($event['message']['type'] ?? '') === 'text') {
            if (! $store->auto_reply_enabled) return;   // OFF のときは反応しない
            $line->reply($event['replyToken'], "ギフトはこちらから贈れます 🎁\n{$giftUrl}");
            return;
        }

        if ($event['type'] === 'postback') {
            $line->reply($event['replyToken'], "ボタンが押されました（data: {$event['postback']['data']}）");
        }
    }
}
