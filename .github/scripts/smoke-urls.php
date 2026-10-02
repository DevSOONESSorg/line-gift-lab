<?php

// =====================================================
// 全画面の巡回（CI 用）：開いて確かめる URL の一覧を作る
//   php .github/scripts/smoke-urls.php > urls.txt
//
// GET のルートを全部取り出し、{store} や {order} のような「穴」には
// 初期データ（シーダー）にある本物の値を入れます。
// LIFF の画面には、その画面を開く人（お客さん・オーナー）の mock_uid を付けます。
// 値が見つからない穴があるルートは、理由を付けて飛ばします（"# skip" の行）。
// =====================================================

use App\Models\Agent;
use App\Models\Menu;
use App\Models\MenuTemplate;
use App\Models\Mock\Channel;
use App\Models\Mock\LineUser;
use App\Models\Mock\OfficialAccount;
use App\Models\Mock\Provider;
use App\Models\Mock\RichMenu;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// 疑似スマホの2人（お客さん・オーナー）。LIFF の画面はこの2人として開く
$customerUid = LineUser::where('phone', 'customer')->value('user_id');
$ownerUid = LineUser::where('phone', 'owner')->value('user_id');

$store = Store::where(['is_approved' => true, 'owner_line_user_id' => $ownerUid])->whereHas('menus')->first()
    ?? Store::where('is_approved', true)->whereHas('menus')->first();
$order = Order::where('store_id', $store?->id)->first() ?? Order::first();

// 疑似 Manager / Developers は smoke.sh が「会社の作業アカウント」に切り替えてから開く。
// その人がメンバーになっている公式アカウント・チャネル・プロバイダーを選ぶ
$account = 'company';
$mock = DB::connection('mockline');
$oa = OfficialAccount::find($mock->table('oa_members')->where('account_id', $account)->value('official_account_id'));

// 穴の名前 → 入れる値
$values = [
    'agent' => Agent::value('id'),
    'menu_template' => MenuTemplate::value('id'),
    'order' => $order?->id,
    'store' => $store?->id,
    'menu' => Menu::where('store_id', $store?->id)->value('id'),
    'customer' => $order?->customer_id,
    'slug' => $store?->slug,
    'channel' => $mock->table('channel_roles')->where('account_id', $account)->value('channel_id') ?? Channel::value('id'),
    'provider' => $mock->table('provider_members')->where('account_id', $account)->value('provider_id') ?? Provider::value('id'),
    'oa' => $oa?->id,
    'richMenu' => RichMenu::where('official_account_id', $oa?->id)->value('id') ?? RichMenu::value('id'),
    'phone' => 'customer',
    'userId' => LineUser::value('user_id'),
];

// わざと巡回しないルート（理由つき）
$skip = [
    'storage/{path}' => 'ファイル置き場（画面ではない）',
    'media/thank-videos/{order}' => '署名付きURLでしか開けない（403 が正しい動き）',
    'mock/manager/invite/{token}' => '招待URLは操作の途中で作られる',
];

foreach (Route::getRoutes() as $route) {
    if (! in_array('GET', $route->methods(), true)) continue;
    $uri = $route->uri();
    if (isset($skip[$uri])) { echo "# skip {$uri}（{$skip[$uri]}）\n"; continue; }

    $missing = null;
    $url = preg_replace_callback('/\{(\w+)\??\}/', function ($m) use ($values, &$missing) {
        $v = $values[$m[1]] ?? null;
        if ($v === null) $missing = $m[1];
        return (string) $v;
    }, $uri);
    if ($missing) { echo "# skip {$uri}（{$missing} の値が初期データにない）\n"; continue; }

    // LIFF の画面は「誰が開いたか」が必要。オーナー用の画面はオーナー、それ以外はお客さんのスマホとして開く
    if (str_starts_with($uri, 'liff/')) {
        $uid = str_starts_with($uri, 'liff/manage') || str_starts_with($uri, 'liff/register') ? $ownerUid : $customerUid;
        if ($uid) $url .= '?mock_uid='.urlencode($uid);
    }
    echo '/'.ltrim($url, '/')."\n";
}
