<?php

namespace App\Models\Mock;

// スマホ側のLINEユーザー（体験ではあなた1人）
class LineUser extends MockModel
{
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = 'user_id';
    protected $keyType = 'string';

    public static function me(): self { return self::firstOrFail(); }
}
