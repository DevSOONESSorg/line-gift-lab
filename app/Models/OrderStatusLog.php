<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatusLog extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['created_at' => 'datetime'];

    // DB の既定値（CURRENT_TIMESTAMP）は UTC になるので、アプリの時刻（日本時間）で入れる
    protected static function booted(): void
    {
        static::creating(fn (self $log) => $log->created_at ??= now());
    }
}
