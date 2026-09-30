<?php

namespace App\Models\Mock;

// スマホ側のLINEユーザー
//   体験では2台：お客さんのスマホ（customer）と、お店のオーナーのスマホ（owner）
class LineUser extends MockModel
{
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = 'user_id';
    protected $keyType = 'string';
    protected $guarded = [];

    public const PHONES = ['customer' => 'お客さん', 'owner' => 'オーナー'];

    public static function me(string $phone = 'customer'): self
    {
        return self::where('phone', $phone)->first() ?? self::firstOrFail();
    }

    public function phoneLabel(): string
    {
        return self::PHONES[$this->phone] ?? 'スマホ';
    }
}
