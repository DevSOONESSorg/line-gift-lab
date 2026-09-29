<?php

namespace App\Models\Mock;

use Illuminate\Support\Facades\DB;

class OfficialAccount extends MockModel
{
    protected $casts = ['auto_reply_on' => 'boolean', 'greeting_on' => 'boolean', 'is_platform' => 'boolean'];

    public function messagingChannel() { return $this->hasOne(Channel::class)->where('type', 'messaging'); }
    public function richMenus() { return $this->hasMany(RichMenu::class); }

    public function roleOf(string $accountId): ?string
    {
        return DB::connection('mockline')->table('oa_members')->where(['official_account_id' => $this->id, 'account_id' => $accountId])->value('role');
    }

    public function isFriend(string $userId): bool
    {
        return DB::connection('mockline')->table('friends')->where(['official_account_id' => $this->id, 'user_id' => $userId, 'blocked' => false])->exists();
    }

    public static function byBasicId(?string $basicId): ?self
    {
        return self::whereRaw('lower(basic_id) = ?', [mb_strtolower(trim((string) $basicId))])->first();
    }

    public static function of(string $accountId)
    {
        return self::join('oa_members', 'oa_members.official_account_id', '=', 'official_accounts.id')
            ->where('oa_members.account_id', $accountId)->orderBy('official_accounts.id')
            ->get(['official_accounts.*', 'oa_members.role']);
    }
}
