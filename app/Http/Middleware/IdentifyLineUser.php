<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use App\Models\Mock\LineUser;
use Closure;
use Illuminate\Http\Request;

// =====================================================
// LIFF の画面で「いま開いているのは誰か」を決める
//
//   疑似スマホから開いたとき … URL に ?mock_uid=U... が付いてくる
//   本物のLINEで開いたとき   … 画面の LIFF SDK がプロフィールを取り出し、/liff/session に送ってくる → セッションに覚える
//
// 疑似スマホは2台（お客さん・オーナー）あり、同じブラウザの中で同時に開いています。
// セッション（ブラウザで1つ）だけで覚えると2台の区別がつかなくなるので、
// 疑似スマホでは「どのスマホか」を毎回のリクエストに付けて送ります（mock_uid）。
//   1. URL の ?mock_uid=      … リンク・フォームの送り先（画面の JavaScript が付け足す）
//   2. フォームの mock_uid    … POST のとき
//   3. 直前の画面の URL（Referer）… フォーム送信のあとの「リダイレクト先」を開くとき
//
// ※ 教材では、送られてきたユーザーIDをそのまま信じています。
//   本物のサービスでは「IDトークン」をサーバーで検証して、なりすましを防ぎます（コースCの発展）
// =====================================================
class IdentifyLineUser
{
    public function handle(Request $request, Closure $next)
    {
        $uid = $request->query('mock_uid') ?: $request->input('mock_uid') ?: $this->fromReferer($request);
        $mockUser = $uid ? LineUser::find($uid) : null;

        if ($mockUser) {
            $lu = ['id' => $mockUser->user_id, 'name' => $mockUser->display_name, 'mock' => true, 'phone' => $mockUser->phone];
            $request->session()->put('line_user', $lu);
        } else {
            $lu = $request->session()->get('line_user');
        }
        if ($liff = $request->query('mock_liff')) {
            $request->session()->put('opened_liff', $liff);
        }

        $customer = $lu ? Customer::fromLine($lu['id'], $lu['name']) : null;
        $request->attributes->set('customer', $customer);
        view()->share('lineUser', $lu);
        view()->share('customer', $customer);
        view()->share('mockUid', ($lu['mock'] ?? false) ? $lu['id'] : null);

        return $next($request);
    }

    private function fromReferer(Request $request): ?string
    {
        $ref = parse_url((string) $request->headers->get('referer'));
        if (($ref['host'] ?? null) !== $request->getHost() || ! str_starts_with($ref['path'] ?? '', '/liff')) return null;
        parse_str($ref['query'] ?? '', $q);
        return $q['mock_uid'] ?? null;
    }
}
