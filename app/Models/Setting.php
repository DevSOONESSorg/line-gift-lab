<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// サービス全体の設定（key → value）。運営の公式LINEの値などを入れる
class Setting extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = 'key';
    protected $keyType = 'string';
    protected $guarded = [];

    public static function get(string $key, string $default = ''): string
    {
        return self::find($key)?->value ?? $default;
    }

    public static function put(string $key, string $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
