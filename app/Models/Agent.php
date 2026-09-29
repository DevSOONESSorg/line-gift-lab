<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// 代理店。parent_id が空なら1次、あれば2次
class Agent extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'reward_rate' => 'decimal:2'];

    public function parent(): BelongsTo { return $this->belongsTo(Agent::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(Agent::class, 'parent_id'); }
    public function stores(): HasMany { return $this->hasMany(Store::class); }

    public function isPrimary(): bool { return $this->parent_id === null; }

    // 報酬の対象になる店舗：直接紹介した店舗 ＋（1次なら）2次代理店経由の店舗
    public function rewardStoreIds(): array
    {
        $ids = $this->stores()->pluck('id')->all();
        if ($this->isPrimary()) {
            $ids = array_merge($ids, Store::whereIn('agent_id', $this->children()->pluck('id'))->pluck('id')->all());
        }
        return $ids;
    }

    public static function newReferralCode(): string
    {
        do { $code = (string) random_int(100000, 999999); } while (self::where('referral_code', $code)->exists());
        return $code;
    }
}
