<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// =====================================================
// 売上集計
// 「受取済み」「お礼済み」の注文だけを売上として数える（リクエスト中や期限切れは数えない）
// 集計は SQL の GROUP BY で行う。下の $sql を裏側ビューのように画面にも出して、読めるようにしている
// =====================================================
class SalesController extends Controller
{
    public function __invoke(Request $r)
    {
        $start = $r->input('start_date', now()->startOfMonth()->toDateString());
        $end = $r->input('end_date', now()->endOfMonth()->toDateString());
        $settled = OrderStatus::settled();

        $base = DB::table('orders')->whereIn('orders.status', $settled)
            ->whereDate('orders.created_at', '>=', $start)->whereDate('orders.created_at', '<=', $end);

        $summary = (clone $base)->selectRaw('COUNT(*) AS count, COALESCE(SUM(amount),0) AS sales, COALESCE(SUM(commission),0) AS commission')->first();

        $byStoreQuery = (clone $base)->join('stores', 'stores.id', '=', 'orders.store_id')
            ->groupBy('stores.id', 'stores.name')
            ->selectRaw('stores.name, COUNT(*) AS count, SUM(orders.amount) AS sales, SUM(orders.commission) AS commission, SUM(orders.amount - orders.commission) AS payout')
            ->orderByDesc('sales');
        $byStore = $byStoreQuery->get();

        $monthly = DB::table('orders')->whereIn('status', $settled)
            ->selectRaw("strftime('%Y-%m', created_at) AS month, COUNT(*) AS count, SUM(amount) AS sales, SUM(commission) AS commission")
            ->groupBy('month')->orderByDesc('month')->limit(12)->get();

        $rateStore = $r->store_id ? Store::with('monthlyRates')->find($r->store_id) : null;

        return view('admin.sales', [
            'start' => $start, 'end' => $end, 'summary' => $summary, 'byStore' => $byStore, 'monthly' => $monthly,
            'stores' => Store::orderBy('name')->get(), 'rateStore' => $rateStore,
            'sql' => $byStoreQuery->toRawSql(),
        ]);
    }
}
