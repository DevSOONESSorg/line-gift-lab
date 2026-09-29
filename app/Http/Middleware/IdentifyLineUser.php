<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use App\Models\Mock\LineUser;
use Closure;
use Illuminate\Http\Request;

// =====================================================
// LIFF の画面で「いま開いているのは誰か」を決める
//
//   疑似スマホから開いたとき … URL に ?mock_uid=U... が付いてくる → セッションに覚える
//   本物のLINEで開いたとき   … 画面の LIFF SDK がプロフィールを取り出し、/liff/session に送ってくる
//
// ※ 教材では、送られてきたユーザーIDをそのまま信じています。
//   本物のサービスでは「IDトークン」をサーバーで検証して、なりすましを防ぎます（コースCの発展）
// =====================================================
class IdentifyLineUser
{
    public function handle(Request $request, Closure $next)
    {
        if ($uid = $request->query('mock_uid')) {
            $user = LineUser::find($uid);
            if ($user) {
                $request->session()->put('line_user', ['id' => $user->user_id, 'name' => $user->display_name, 'mock' => true]);
            }
        }
        if ($liff = $request->query('mock_liff')) {
            $request->session()->put('opened_liff', $liff);
        }

        $lu = $request->session()->get('line_user');
        $customer = $lu ? Customer::fromLine($lu['id'], $lu['name']) : null;
        $request->attributes->set('customer', $customer);
        view()->share('lineUser', $lu);
        view()->share('customer', $customer);

        return $next($request);
    }
}
