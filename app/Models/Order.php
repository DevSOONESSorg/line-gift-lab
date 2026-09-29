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
}
