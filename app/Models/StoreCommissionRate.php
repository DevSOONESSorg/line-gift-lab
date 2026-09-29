<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// 月別手数料率（その月だけ手数料率を変える）
class StoreCommissionRate extends Model
{
    protected $guarded = [];

    protected $casts = ['rate' => 'decimal:2'];
}
