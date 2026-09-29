<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Command;

// 教材用：注文の期限を「過去」にずらして、期限切れの動きをすぐ試せるようにする
//   php artisan lab:time-travel 15     … 注文 #15 の期限を1分前にする
class TimeTravel extends Command
{
    protected $signature = 'lab:time-travel {order : 注文ID}';
    protected $description = '（教材用）注文の期限を過去にずらす';

    public function handle(): int
    {
        $order = Order::find($this->argument('order'));
        if (! $order || ! in_array($order->status, [OrderStatus::Requested, OrderStatus::AwaitingPayment], true)) {
            $this->error('「リクエスト中」か「入金待ち」の注文IDを指定してください。');
            return self::FAILURE;
        }
        $order->update(['expires_at' => now()->subMinute()]);
        $this->info("注文 #{$order->id} の期限を1分前にしました。1分以内にスケジューラーが期限切れにします（すぐ試すなら php artisan orders:expire）。");
        return self::SUCCESS;
    }
}
