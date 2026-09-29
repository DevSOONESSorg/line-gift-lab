<?php

namespace App\Enums;

// =====================================================
// 注文の状態（ステータス）と、状態の移り変わり（状態遷移）
//
//  [カード]   リクエスト中 ──受け取る──▶ 受取済み ──お礼──▶ お礼済み
//                 │                           │               │
//                 └─(与信の期限切れ)─▶ 期限切れ   └──────┬────────┘
//                                                        ▼
//                                              返金処理待ち ─▶ 返金済み
//
//  [銀行振込] 入金待ち ──入金確認──▶ リクエスト中 ─▶ …（カードと同じ）
//                 └─(入金期限切れ)─▶ キャンセル
//
// 「ありえない移り変わり」（例：期限切れ → 受取済み）はここで止めます。
// =====================================================
enum OrderStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case Requested = 'requested';
    case Received = 'received';
    case Thanked = 'thanked';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case RefundPending = 'refund_pending';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingPayment => '入金待ち (振込)',
            self::Requested => 'リクエスト中',
            self::Received => '受取済み',
            self::Thanked => 'お礼済み',
            self::Expired => '期限切れ (カード)',
            self::Cancelled => 'キャンセル (未入金)',
            self::RefundPending => '返金処理待ち',
            self::Refunded => '返金済み',
        };
    }

    // Bootstrap のバッジの色
    public function color(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'warning',
            self::Requested => 'info',
            self::Received => 'primary',
            self::Thanked => 'success',
            self::Expired, self::Cancelled => 'secondary',
            self::RefundPending => 'danger',
            self::Refunded => 'dark',
        };
    }

    // この状態から移ってよい先
    public function next(): array
    {
        return match ($this) {
            self::AwaitingPayment => [self::Requested, self::Cancelled],
            self::Requested => [self::Received, self::Expired],
            self::Received => [self::Thanked, self::RefundPending],
            self::Thanked => [self::RefundPending],
            self::RefundPending => [self::Refunded],
            self::Expired, self::Cancelled, self::Refunded => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }

    // 売上として数える状態（お店に振り込む対象）
    public static function settled(): array
    {
        return [self::Received->value, self::Thanked->value];
    }
}
