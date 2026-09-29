<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Inside;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// 管理画面のログイン（セッション＋Cookie 方式）
class LoginController extends Controller
{
    public function show() { return view('admin.login'); }

    public function login(Request $request)
    {
        $cred = $request->validate(['email' => 'required|email', 'password' => 'required']);
        if (! Auth::attempt($cred, $request->boolean('remember'))) {
            Inside::ng('admin', "ログイン失敗（{$cred['email']}）");
            return back()->withInput($request->only('email'))->withErrors(['email' => 'メールアドレスかパスワードが違います。']);
        }
        $request->session()->regenerate();   // セッション固定攻撃の対策：ログインしたらセッションIDを作り直す
        Inside::ok('admin', "ログインしました（{$cred['email']}）");
        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}
