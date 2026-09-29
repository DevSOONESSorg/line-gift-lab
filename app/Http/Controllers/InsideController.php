<?php

namespace App\Http\Controllers;

use App\Support\Inside;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// 裏側ビュー：スマホ・LINE社・自社サーバー・管理画面の間で起きたことを時間順に表示
class InsideController extends Controller
{
    public function index()
    {
        return view('inside', ['logs' => DB::connection('inside')->table('logs')->orderByDesc('id')->limit(300)->get(), 'sides' => Inside::SIDES]);
    }

    public function logs(Request $r)
    {
        return DB::connection('inside')->table('logs')->where('id', '>', (int) $r->query('after'))->orderByDesc('id')->limit(300)->get();
    }

    public function clear()
    {
        DB::connection('inside')->table('logs')->delete();
        return redirect()->route('inside');
    }
}
