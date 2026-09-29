<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Store;
use App\Services\Line\LineClient;
use App\Support\Inside;

// =====================================================
// LINE でのお知らせ係
//
// 【フォールバック（切り替え）の設計】
//   店舗専用の Messaging API が設定されていて、お客さんがその店の公式LINEから来た
//     → 店舗のチャネルから送る
//   それ以外（共通掲載から来た／店舗が未設定）
//     → 運営の共通チャネルから送る
// 未設定のお店でも、サービスが止まらずに動くための作りです（本物の管理画面の「設定整合性」の説明と同じ）
// =====================================================
class Notifier
{
    public static function channelForOrder(Order $order): LineClient
    {
        $store = $order->store;
        if ($order->route === 'original' && $store->hasOwnMessagingChannel()) {
            return LineClient::forStore($store);
        }
        if ($order->route === 'original') {
            Inside::info('app', "「{$store->name}」は店舗専用チャネルが未設定なので、共通チャネルから送ります（フォールバック）");
        }
        return LineClient::platform();
    }

    public static function toCustomer(Order $order, string $text): array
    {
        return self::channelForOrder($order)->push($order->customer->line_user_id, $text);
    }

    // 店舗オーナーへのお知らせは、いつも運営の共通チャネルから
    public static function toOwner(Store $store, string $text): ?array
    {
        if (! $store->owner_line_user_id) return null;
        return LineClient::platform()->push($store->owner_line_user_id, $text);
    }
}
