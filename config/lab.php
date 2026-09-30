<?php

// =====================================================
// 教材の「決まりごと」をまとめた設定
// config('lab.xxx') で読み出します
// =====================================================

return [
    // 環境名（dev / stg / prod）。管理画面の右下に表示する
    'stage' => env('APP_STAGE', 'dev'),

    // 新しい店舗の手数料率（%）
    'default_commission_rate' => 10,

    // カード決済：仮押さえ（与信）の有効日数。これを過ぎても「受け取る」されないと期限切れ
    'card_authorization_days' => 7,

    // 銀行振込：入金期限の日数。これを過ぎると自動キャンセル
    'bank_transfer_days' => 3,

    // 振込先（銀行振込を選んだお客さんに見せる。体験用の架空の口座）
    'bank_transfer_account' => 'さくら銀行 那覇支店 普通 0000000 オクリギフト（カ',

    // テストカード
    'test_cards' => [
        '4242424242424242' => 'ok',        // 成功
        '4000000000000002' => 'declined',  // 拒否
    ],

    // コースB：公開URL（空なら cloudflared から自動取得）
    'public_url' => rtrim((string) env('PUBLIC_URL', ''), '/'),
    'tunnel_metrics' => env('TUNNEL_METRICS', ''),
    'host_port' => env('PORT', 3000),

    // 疑似LINE：応答メッセージ・あいさつの初期値
    'default_auto_reply' => "メッセージありがとうございます！\n申し訳ありませんが、このアカウントから個別のご返信はできません。",
    'default_greeting' => "{Nickname}さん はじめまして！{AccountName}です。\n友だち追加ありがとうございます！",

    // 疑似LINE：リッチメニューのテンプレート（x, y, 幅 w, 高さ h は画像全体を 1 とした割合）
    'richmenu_templates' => [
        'large-6' => ['label' => '大・6分割（3×2）', 'size' => 'large', 'grid' => [3, 2]],
        'large-4' => ['label' => '大・4分割（2×2）', 'size' => 'large', 'grid' => [2, 2]],
        'large-3' => ['label' => '大・3分割（上1＋下2）', 'size' => 'large', 'areas' => [[0, 0, 1, .5], [0, .5, .5, .5], [.5, .5, .5, .5]]],
        'large-1' => ['label' => '大・1分割', 'size' => 'large', 'grid' => [1, 1]],
        'small-3' => ['label' => '小・3分割（横3）', 'size' => 'small', 'grid' => [3, 1]],
        'small-2' => ['label' => '小・2分割（横2）', 'size' => 'small', 'grid' => [2, 1]],
        'small-1' => ['label' => '小・1分割', 'size' => 'small', 'grid' => [1, 1]],
    ],
    'richmenu_sizes' => [
        'large' => [[2500, 1686], [1200, 810], [800, 540]],
        'small' => [[2500, 843], [1200, 405], [800, 270]],
    ],
];
