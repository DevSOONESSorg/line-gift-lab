<?php

namespace App\Models\Mock;

use Illuminate\Database\Eloquent\Model;

// 疑似LINE（LINE社の役）のモデルは、すべて mockline 接続（database/mockline.sqlite）を使う
abstract class MockModel extends Model
{
    protected $connection = 'mockline';
    protected $guarded = [];
}
