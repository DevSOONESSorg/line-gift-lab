<?php

namespace App\Http\Controllers\Liff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// 本物のLINE（LIFF SDK）で開かれたとき、画面の JavaScript がプロフィールを送ってくる
// ※ 教材では送られてきた値をそのまま信じる。本物は IDトークンをサーバーで検証する（コースC）
class SessionController extends Controller
{
    public function store(Request $r)
    {
        $data = $r->validate(['userId' => ['required', 'regex:/^U[0-9a-f]{32}$/'], 'displayName' => 'required|max:100']);
        $r->session()->put('line_user', ['id' => $data['userId'], 'name' => $data['displayName'], 'mock' => false]);
        return ['ok' => true];
    }
}
