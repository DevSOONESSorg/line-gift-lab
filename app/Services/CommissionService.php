<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StoreCommissionRate;
use Carbon\CarbonInterface;

// 手数料率の決め方：その月の「月別手数料率」があればそれ、なければ「デフォルト手数料率」
class CommissionService
{
    public static function rateFor(Store $store, ?CarbonInterface $at = null): float
    {
        $at ??= now();
        $monthly = StoreCommissionRate::where(['store_id' => $store->id, 'year' => $at->year, 'month' => $at->month])->value('rate');
        return (float) ($monthly ?? $store->commission_rate);
    }

    public static function commission(int $amount, float $rate): int
    {
        return (int) round($amount * $rate / 100);
    }
}
