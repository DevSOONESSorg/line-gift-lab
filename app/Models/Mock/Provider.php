<?php

namespace App\Models\Mock;

use Illuminate\Support\Facades\DB;

// プロバイダー＝会社の「箱」。チャネルはどれかの箱に入る
class Provider extends MockModel
{
    public $timestamps = false;

    public function channels() { return $this->hasMany(Channel::class); }

    public static function of(string $accountId)
    {
        $ids = DB::connection('mockline')->table('provider_members')->where('account_id', $accountId)->pluck('provider_id');
        return self::whereIn('id', $ids)->orderBy('id')->get();
    }

    public function hasMember(string $accountId): bool
    {
        return DB::connection('mockline')->table('provider_members')->where(['provider_id' => $this->id, 'account_id' => $accountId])->exists();
    }
}
