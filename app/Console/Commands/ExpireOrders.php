<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

// 期限切れの注文をまとめて処理する
//   カード：与信（仮押さえ）の期限までに「受け取る」されなかった → 期限切れ
//   振込  ：入金期限までに入金がなかった → キャンセル
class ExpireOrders extends Command
{
    protected $signature = 'orders:expire';
    protected $description = '期限が過ぎた注文を「期限切れ」「キャンセル」にする';

    public function handle(): int
    {
        $done = OrderService::expireOverdue();
        if ($done['expired'] || $done['cancelled']) {
            $this->info("期限切れ {$done['expired']} 件 ／ キャンセル {$done['cancelled']} 件");
        }
        return self::SUCCESS;
    }
}
