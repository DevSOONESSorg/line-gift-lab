<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// お客さん（LINEユーザー）。管理画面の「ユーザー管理」はこの一覧
class Customer extends Model
{
    protected $guarded = [];

    protected $casts = ['id_verified_at' => 'datetime'];

    public function orders(): HasMany { return $this->hasMany(Order::class); }

    public function hasCard(): bool { return filled($this->card_last4); }

    // LINEユーザーIDから探す。いなければ作る（初めて来た人）
    public static function fromLine(string $userId, string $displayName): self
    {
        return self::firstOrCreate(['line_user_id' => $userId], ['line_display_name' => $displayName]);
    }
}
