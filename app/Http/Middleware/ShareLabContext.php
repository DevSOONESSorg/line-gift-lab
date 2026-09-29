<?php

namespace App\Http\Middleware;

use App\Models\Mock\Account;
use Closure;
use Illuminate\Http\Request;

// どの画面でも使う共通の値を、画面（Blade）に渡す
class ShareLabContext
{
    public function handle(Request $request, Closure $next)
    {
        $accountId = $request->session()->get('mock_account', 'personal');
        view()->share('stage', config('lab.stage'));
        view()->share('mockAccount', Account::find($accountId) ?? Account::find('personal'));
        view()->share('mockAccounts', Account::orderByDesc('id')->get());
        return $next($request);
    }
}
