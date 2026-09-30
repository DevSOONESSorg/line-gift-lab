<?php

namespace App\Services;

use App\Models\MenuTemplate;
use App\Models\Mock\Channel;
use App\Models\Mock\LiffApp;
use App\Models\Mock\LineUser;
use App\Models\Mock\OfficialAccount;
use App\Models\Setting;
use App\Models\Store;
use App\Services\MockLine\MockLine;
use App\Support\Inside;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// =====================================================
// 構築の課題（シナリオ）
//
// 本番と同じ「スタート地点」のお店を作る。構築ナビでお店を選ぶたびに、ここで作り直す（＝新規構築）。
//   ・オーナーが運営LINEのQRから出店登録済み（未承認・slug は仮の store-番号）
//   ・お店は自分の公式LINEを持っていて、お客さんも友だち追加している
//   ・リッチメニューもある。「ギフトを贈る」ボタンだけ、まだどこにもつながっていない
//
// 課題を増やすときは SCENARIOS に1つ足す（店舗情報・商品・リッチメニュー）。
// =====================================================
class BuildScenario
{
    public const SCENARIOS = [
        'club-azure' => [
            'label' => 'クラブ アズール（ラウンジ・那覇市）',
            'slug' => 'club-azure',   // 課題で決める slug（構築ナビに「これを入れる」と出る）
            'store' => [
                'name' => 'クラブ アズール', 'description' => '那覇のラウンジ。スタッフへのギフトはこちらから。', 'image_color' => '#1d4ed8',
                'prefecture' => '沖縄県', 'city' => '那覇市', 'address' => '松山1-2-3 アズールビル2F', 'tel' => '098-000-0004', 'business_license_number' => '那保第000号（架空）',
                'representative_name' => '東 あおい', 'representative_tel' => '090-0000-0004',
                'bank_name' => 'みなと銀行', 'bank_branch' => '松山支店', 'bank_account_type' => '普通', 'bank_account_number' => '7654321', 'bank_account_name' => 'アズマ アオイ',
            ],
            'menus' => [7, 6, 4],   // 商品テンプレートの番号
            'industry' => 'ナイトワーク',
            'richmenu' => ['title' => 'お店のメニュー', 'template' => 'large-4', 'image' => 'azure-menu.png', 'actions' => [
                ['type' => 'none', 'value' => '', 'label' => 'ギフトを贈る'],   // ← ここに贈る画面（LIFF）の URL を入れるのが課題
                ['type' => 'text', 'value' => 'お店の情報', 'label' => 'お店の情報'],
                ['type' => 'text', 'value' => '営業時間', 'label' => '営業時間'],
                ['type' => 'link', 'value' => 'https://www.instagram.com/', 'label' => 'Instagram'],
            ]],
        ],
        'bar-tsukiakari' => [
            'label' => 'バー 月あかり（バー・沖縄市）',
            'slug' => 'bar-tsukiakari',
            'store' => [
                'name' => 'バー 月あかり', 'description' => '沖縄市のオーセンティックバー。バーテンダーへの一杯はこちらから。', 'image_color' => '#7c2d12',
                'prefecture' => '沖縄県', 'city' => '沖縄市', 'address' => '中央2-3-4 月見ビル1F', 'tel' => '098-000-0005', 'business_license_number' => '中保第000号（架空）',
                'representative_name' => '月城 ひかる', 'representative_tel' => '090-0000-0005',
                'bank_name' => 'みなと銀行', 'bank_branch' => 'コザ支店', 'bank_account_type' => '普通', 'bank_account_number' => '1112223', 'bank_account_name' => 'ツキシロ ヒカル',
            ],
            'menus' => [4, 6, 7],
            'industry' => '飲食店',
            // 大・3分割（上1＋下2）。「贈る」は左下の B
            'richmenu' => ['title' => '月あかりメニュー', 'template' => 'large-3', 'image' => 'tsukiakari-menu.png', 'actions' => [
                ['type' => 'text', 'value' => 'お店の情報', 'label' => 'お店の情報'],
                ['type' => 'none', 'value' => '', 'label' => 'ギフトを贈る'],
                ['type' => 'text', 'value' => '予約したい', 'label' => 'ご予約'],
            ]],
        ],
        'yakiniku-harusaki' => [
            'label' => '焼肉 はるさき（焼肉店・浦添市）',
            'slug' => 'yakiniku-harusaki',
            'store' => [
                'name' => '焼肉 はるさき', 'description' => '浦添の焼肉店。がんばるスタッフへの差し入れはこちらから。', 'image_color' => '#b91c1c',
                'prefecture' => '沖縄県', 'city' => '浦添市', 'address' => '港川5-6-7', 'tel' => '098-000-0006', 'business_license_number' => '浦保第000号（架空）',
                'representative_name' => '春崎 たける', 'representative_tel' => '090-0000-0006',
                'bank_name' => 'みなと銀行', 'bank_branch' => '浦添支店', 'bank_account_type' => '普通', 'bank_account_number' => '3334445', 'bank_account_name' => 'ハルサキ タケル',
            ],
            'menus' => [7, 9, 6],
            'industry' => '飲食店',
            // 大・6分割（3×2）。「贈る」は右下の F
            'richmenu' => ['title' => 'はるさきメニュー', 'template' => 'large-6', 'image' => 'harusaki-menu.png', 'actions' => [
                ['type' => 'text', 'value' => 'メニュー', 'label' => 'メニュー'],
                ['type' => 'text', 'value' => 'お店の情報', 'label' => 'お店の情報'],
                ['type' => 'text', 'value' => '営業時間', 'label' => '営業時間'],
                ['type' => 'text', 'value' => 'クーポン', 'label' => 'クーポン'],
                ['type' => 'link', 'value' => 'https://www.instagram.com/', 'label' => 'Instagram'],
                ['type' => 'none', 'value' => '', 'label' => 'スタッフに贈る'],
            ]],
        ],
    ];

    // リッチメニューの中で「贈る」ボタン（まだつながっていない場所）：['letter' => 'B', 'label' => 'ギフトを贈る', 'others' => ['A（お店の情報）', ...]]
    public static function giftArea(string $key): array
    {
        $letters = range('A', 'Z'); $gift = null; $others = [];
        foreach (self::SCENARIOS[$key]['richmenu']['actions'] as $i => $a) {
            if ($a['type'] === 'none' && ! $gift) $gift = ['letter' => $letters[$i], 'label' => $a['label']];
            else $others[] = $letters[$i].'（'.$a['label'].'）';
        }
        return ($gift ?? ['letter' => 'A', 'label' => 'ギフトを贈る']) + ['others' => $others];
    }

    public static function storeId(string $key): ?int { return Setting::get("scenario.{$key}.store") ? (int) Setting::get("scenario.{$key}.store") : null; }

    public static function keyOf(Store $store): ?string
    {
        foreach (array_keys(self::SCENARIOS) as $k) if (self::storeId($k) === $store->id) return $k;
        return null;
    }

    // スタート地点のお店を作る（前に作ったものがあれば、LINE側の設定もふくめて全部消してから）
    public static function reset(string $key): Store
    {
        $def = self::SCENARIOS[$key];
        self::destroy($key);

        $owner = LineUser::me('owner');
        $guest = LineUser::me('customer');

        $store = Store::create($def['store'] + [
            'slug' => 'tmp-'.$key, 'commission_rate' => 10, 'is_approved' => false, 'is_listed_in_directory' => false,
            'wants_original' => true, 'owner_line_user_id' => $owner->user_id,
        ]);
        $store->update(['slug' => "store-{$store->id}"]);   // 出店登録したときと同じ、仮の slug
        foreach ($def['menus'] as $tid) {
            if ($t = MenuTemplate::find($tid)) $store->menus()->create(['menu_template_id' => $t->id, 'name' => $t->name, 'price' => $t->reference_price]);
        }

        // お店がもともと持っている公式LINE（持ち主はオーナー＝個人アカウント）とリッチメニュー
        $oa = MockLine::createOfficialAccount($def['store']['name'], 'personal', $def['industry']);
        $rm = $def['richmenu'];
        $path = "richmenus/scenario-{$oa->id}.png";
        Storage::disk('public')->put($path, file_get_contents(database_path("seeders/images/{$rm['image']}")));
        [$w, $h] = getimagesize(database_path("seeders/images/{$rm['image']}"));
        $oa->richMenus()->create(['title' => $rm['title'], 'template' => $rm['template'], 'image_path' => $path, 'width' => $w, 'height' => $h, 'actions' => $rm['actions'], 'is_default' => true]);

        // お客さんは、もともとこのお店の友だち
        DB::connection('mockline')->table('friends')->insert(['official_account_id' => $oa->id, 'user_id' => $guest->user_id, 'blocked' => false]);
        MockLine::greet($oa, $guest);

        Setting::put("scenario.{$key}.store", $store->id);
        Setting::put("scenario.{$key}.oa", $oa->id);
        Inside::info('app', "構築の課題「{$store->name}」をスタート地点に戻しました", "出店登録済み（未承認・slug {$store->slug}）／公式LINE {$oa->basic_id}／リッチメニューの「ギフトを贈る」は未設定");
        return $store;
    }

    // 前回の構築をすべて消す（自社のお店・注文と、LINE側の公式アカウント・チャネル・LIFF）
    private static function destroy(string $key): void
    {
        $store = Store::find(self::storeId($key));
        if ($store) {
            // この店用に作った LINEログインチャネル（LIFF の行き先がこの店のもの）
            $loginIds = LiffApp::where('endpoint_url', 'like', '%/liff/s/'.$store->slug)->pluck('channel_id')
                ->merge(LiffApp::where('endpoint_url', 'like', '%/liff/s/'.self::SCENARIOS[$key]['slug'])->pluck('channel_id'))->unique();
            Channel::where('type', 'login')->where(fn ($q) => $q->whereIn('id', $loginIds)->orWhere('name', 'like', $store->name.'%'))
                ->whereNull('official_account_id')->get()->each->delete();
            $store->orders()->each(fn ($o) => $o->delete());
            $store->delete();
        }
        if ($oa = OfficialAccount::find(Setting::get("scenario.{$key}.oa"))) {
            foreach ($oa->richMenus as $m) Storage::disk('public')->delete($m->image_path);
            $oa->delete();   // チャネル・友だち・トーク・リッチメニュー・メンバーもいっしょに消える
        }
    }
}
