<?php

namespace App\Services\Bots;

use App\Services\Line\LineClient;

// 運営の公式LINE（共通チャネル）の bot
class PlatformBot
{
    public static function handle(array $event): void
    {
        $line = LineClient::platform();
        if ($event['type'] === 'follow') {
            $line->reply($event['replyToken'], "おくりギフト(dev) です。\n【ギフトを贈る方】メニューの「お店をさがす」から。\n【お店のオーナーの方】「出店登録」で登録し、「店舗管理」で商品登録やギフトの受け取りができます。");
        } elseif ($event['type'] === 'message') {
            $line->reply($event['replyToken'], '操作は下のメニューからお願いします。');
        }
    }
}
