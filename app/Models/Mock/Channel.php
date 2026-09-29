<?php

namespace App\Models\Mock;

use Illuminate\Support\Facades\DB;

// チャネル（messaging = Messaging API / login = LINEログイン）
class Channel extends MockModel
{
    protected $casts = ['use_webhook' => 'boolean', 'is_published' => 'boolean'];

    public function provider() { return $this->belongsTo(Provider::class); }
    public function officialAccount() { return $this->belongsTo(OfficialAccount::class); }
    public function liffApps() { return $this->hasMany(LiffApp::class); }

    public function roleOf(string $accountId): ?string
    {
        return DB::connection('mockline')->table('channel_roles')->where(['channel_id' => $this->id, 'account_id' => $accountId])->value('role');
    }

    public static function byChannelId(?string $cid): ?self { return $cid ? self::where('channel_id', trim($cid))->first() : null; }
    public static function byToken(?string $token): ?self { return $token ? self::where('access_token', $token)->first() : null; }
}
