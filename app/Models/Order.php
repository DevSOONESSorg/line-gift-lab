<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status' => OrderStatus::class,
        'payment_method' => PaymentMethod::class,
        'commission_rate' => 'decimal:2',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'received_at' => 'datetime',
        'thanked_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function menu(): BelongsTo { return $this->belongsTo(Menu::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function statusLogs(): HasMany { return $this->hasMany(OrderStatusLog::class)->orderBy('id'); }

    // お店に振り込む額 ＝ 売上 − 手数料
    public function payout(): int { return $this->amount - $this->commission; }

    // 送り主の表示名（入力がなければ LINE の表示名）／受取人（空欄ならお店宛）
    public function senderLabel(): string { return $this->sender_name ?: ($this->customer?->line_display_name ?? ''); }
    public function recipientLabel(): string { return $this->recipient_name ?: 'お店宛'; }

    // 注文番号：連番の id はそのまま見せず、推測されにくい10文字をお客さんに見せる
    protected static function booted(): void
    {
        static::creating(function (self $o) {
            if ($o->order_code) return;
            do { $code = strtoupper(\Illuminate\Support\Str::random(10)); $code = strtr($code, ['0' => 'X', 'O' => 'Y', 'I' => 'Z', '1' => 'K', 'L' => 'M']); }
            while (self::where('order_code', $code)->exists());
            $o->order_code = $code;
        });
    }
}
