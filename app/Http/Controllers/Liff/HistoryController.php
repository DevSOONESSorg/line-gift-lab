<?php

namespace App\Http\Controllers\Liff;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class HistoryController extends Controller
{
    private function customer(Request $r)
    {
        $c = $r->attributes->get('customer');
        abort_unless($c, 403, 'LINEの中で開いてください。');
        return $c;
    }

    // 送信履歴
    public function index(Request $r)
    {
        $orders = $this->customer($r)->orders()->with('store')->latest('id')->get();
        return view('liff.history', compact('orders'));
    }

    // 領収書（決済が確定した注文だけ）
    public function receipt(Request $r, Order $order)
    {
        abort_unless($order->customer_id === $this->customer($r)->id, 403);
        abort_unless(in_array($order->status->value, [...OrderStatus::settled(), OrderStatus::RefundPending->value], true), 404, '決済が確定した注文だけ領収書を出せます。');
        return view('liff.receipt', ['order' => $order->load(['store', 'customer'])]);
    }

    // お礼一覧（お店から届いたお礼動画）
    public function thanks(Request $r)
    {
        $orders = $this->customer($r)->orders()->with('store')->where('status', OrderStatus::Thanked)->latest('thanked_at')->get()
            ->each(fn ($o) => $o->video_url = $o->thank_video_path ? URL::temporarySignedRoute('media.thank-video', now()->addMinutes(10), $o) : null);
        return view('liff.thanks', compact('orders'));
    }

    // お礼動画の本体。署名付きURL（期限つき）でしか見られない → routes/web.php の middleware('signed')
    public function video(Order $order)
    {
        abort_unless($order->thank_video_path && Storage::exists($order->thank_video_path), 404);
        return Storage::response($order->thank_video_path);
    }
}
