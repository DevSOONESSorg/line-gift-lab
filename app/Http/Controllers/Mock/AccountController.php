<?php

namespace App\Http\Controllers\Mock;

use App\Http\Controllers\Controller;
use App\Models\Mock\Account;
use Illuminate\Http\Request;

// 疑似 Manager / Developers に「だれとしてログインしているか」を切り替える
class AccountController extends Controller
{
    public function switch(Request $request)
    {
        if (Account::find($request->input('account_id'))) {
            $request->session()->put('mock_account', $request->input('account_id'));
        }
        $back = (string) $request->input('back');
        return redirect(str_starts_with($back, '/mock/') ? $back : route('mock.manager'));
    }
}
