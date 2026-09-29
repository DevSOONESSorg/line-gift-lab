<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// 商品テンプレート：運営が用意する「よくある商品」の見本。店舗はここから選んで商品を作れる
class MenuTemplate extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public const CATEGORIES = ['シャンパン（高級）', 'シャンパン（スタンダード）', 'ワイン', 'その他ドリンク', 'フード'];
}
