<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    protected $guarded = [];

    // シークレットとトークンは「暗号化して」DBに保存する（APP_KEY で暗号化・復号）
    // DBファイルを誰かに見られても、そのままでは使えないようにするため
    protected $casts = [
        'is_approved' => 'boolean',
        'is_listed_in_directory' => 'boolean',
        'require_id_verification' => 'boolean',
        'wants_original' => 'boolean',
        'auto_reply_enabled' => 'boolean',
        'commission_rate' => 'decimal:2',
        'line_messaging_channel_secret' => 'encrypted',
        'line_messaging_channel_access_token' => 'encrypted',
    ];

    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
    public function menus(): HasMany { return $this->hasMany(Menu::class); }
    public function orders(): HasMany { return $this->hasMany(Order::class); }
    public function monthlyRates(): HasMany { return $this->hasMany(StoreCommissionRate::class); }

    public function owner(): BelongsTo { return $this->belongsTo(Customer::class, 'owner_line_user_id', 'line_user_id'); }

    // 店舗専用の Messaging API チャネルが使えるか（使えなければ共通チャネルに切り替える）
    public function hasOwnMessagingChannel(): bool
    {
        return filled($this->line_messaging_channel_id) && filled($this->line_messaging_channel_access_token);
    }

    public function hasOwnLiff(): bool
    {
        return filled($this->liff_id);
    }

    public function maskedBankAccount(): string
    {
        if (! $this->bank_name && ! $this->yucho_symbol) return '—（未登録）';
        if ($this->yucho_symbol) return "ゆうちょ銀行 記号{$this->yucho_symbol} 番号****".substr((string) $this->yucho_number, -3)." {$this->bank_account_name}";
        return "{$this->bank_name} {$this->bank_branch} {$this->bank_account_type} ****".substr((string) $this->bank_account_number, -3)." {$this->bank_account_name}";
    }
}
