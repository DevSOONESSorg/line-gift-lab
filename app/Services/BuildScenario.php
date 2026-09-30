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
// 本番と同じ「スタート地点」のお店を作る。
// 構築ナビのプルダウンで「はじめて」選んだときに作る（お客さんのスマホにお店の公式LINEが追加される）。
// 2回目以降は作り直さず、続きから。完成したお店は残り、贈る機能も使える。
// 「構築履歴をリセット」で、課題のお店をすべて消して最初に戻す（resetAll）。
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
            'label' => 'クラブ アズール（ラウンジ・沖縄 那覇）',
            'slug' => 'club-azure',   // 課題で決める slug
            'store' => [
                'name' => 'クラブ アズール', 'description' => '那覇・松山のラウンジ。スタッフへのシャンパンはこちらから。', 'image_color' => '#1d4ed8',
                'prefecture' => '沖縄県', 'city' => '那覇市', 'address' => '松山1-2-3 アズールビル2F', 'tel' => '098-000-0004', 'business_license_number' => '那保第000号（架空）',
                'representative_name' => '東 あおい', 'representative_tel' => '090-0000-0004',
                'bank_name' => 'みなと銀行', 'bank_branch' => '松山支店', 'bank_account_type' => '普通', 'bank_account_number' => '7654321', 'bank_account_name' => 'アズマ アオイ',
            ],
            'menus' => [4, 7, 9],   // 商品テンプレートの番号
            'industry' => 'ナイトワーク',
            // large-4。「贈る」は A（ギフトを贈る）
            'richmenu' => ['title' => 'お店のメニュー', 'template' => 'large-4', 'image' => 'azure-menu.png', 'actions' => [
                ['type' => 'none', 'value' => '', 'label' => 'ギフトを贈る'],
                ['type' => 'text', 'value' => 'お店の情報', 'label' => 'お店の情報'],
                ['type' => 'text', 'value' => '営業時間', 'label' => '営業時間'],
                ['type' => 'link', 'value' => 'https://www.instagram.com/', 'label' => 'Instagram'],
            ]],
        ],
        'lounge-coral' => [
            'label' => 'ラウンジ コーラル（ラウンジ・沖縄 北谷）',
            'slug' => 'lounge-coral',   // 課題で決める slug
            'store' => [
                'name' => 'ラウンジ コーラル', 'description' => '北谷の海辺のラウンジ。サンセットに乾杯を。', 'image_color' => '#0d9488',
                'prefecture' => '沖縄県', 'city' => '中頭郡北谷町', 'address' => '美浜9-8-7 コーラルテラス3F', 'tel' => '098-000-0007', 'business_license_number' => '中保第000号（架空）',
                'representative_name' => '珊瑚 みお', 'representative_tel' => '090-0000-0007',
                'bank_name' => 'みなと銀行', 'bank_branch' => '北谷支店', 'bank_account_type' => '普通', 'bank_account_number' => '2223334', 'bank_account_name' => 'サンゴ ミオ',
            ],
            'menus' => [4, 5, 7],   // 商品テンプレートの番号
            'industry' => 'ナイトワーク',
            // large-3。「贈る」は C（ギフトを贈る）
            'richmenu' => ['title' => 'コーラルメニュー', 'template' => 'large-3', 'image' => 'coral-menu.png', 'actions' => [
                ['type' => 'text', 'value' => 'お店の情報', 'label' => 'お店の情報'],
                ['type' => 'text', 'value' => '予約したい', 'label' => 'ご予約'],
                ['type' => 'none', 'value' => '', 'label' => 'ギフトを贈る'],
            ]],
        ],
        'club-etoile' => [
            'label' => 'クラブ エトワール（キャバクラ・新宿 歌舞伎町）',
            'slug' => 'club-etoile',   // 課題で決める slug
            'store' => [
                'name' => 'クラブ エトワール', 'description' => '歌舞伎町のキャバクラ。推しのキャストにシャンパンを。', 'image_color' => '#db2777',
                'prefecture' => '東京都', 'city' => '新宿区', 'address' => '歌舞伎町1-0-1 エトワールビル5F', 'tel' => '03-0000-0011', 'business_license_number' => '新保第000号（架空）',
                'representative_name' => '星野 るな', 'representative_tel' => '090-0000-0011',
                'bank_name' => 'みなと銀行', 'bank_branch' => '新宿支店', 'bank_account_type' => '普通', 'bank_account_number' => '4445556', 'bank_account_name' => 'ホシノ ルナ',
            ],
            'menus' => [1, 2, 5, 7],   // 商品テンプレートの番号
            'industry' => 'ナイトワーク',
            // large-6。「贈る」は B（シャンパンを贈る）
            'richmenu' => ['title' => 'エトワールメニュー', 'template' => 'large-6', 'image' => 'etoile-menu.png', 'actions' => [
                ['type' => 'text', 'value' => 'キャスト紹介', 'label' => 'キャスト紹介'],
                ['type' => 'none', 'value' => '', 'label' => 'シャンパンを贈る'],
                ['type' => 'text', 'value' => '出勤情報', 'label' => '出勤情報'],
                ['type' => 'text', 'value' => 'お店の情報', 'label' => 'お店の情報'],
                ['type' => 'text', 'value' => '料金システム', 'label' => '料金システム'],
                ['type' => 'link', 'value' => 'https://www.instagram.com/', 'label' => 'Instagram'],
            ]],
        ],
        'club-luna-noir' => [
            'label' => 'クラブ ルナノワール（キャバクラ・新宿 歌舞伎町）',
            'slug' => 'club-luna-noir',   // 課題で決める slug
            'store' => [
                'name' => 'クラブ ルナノワール', 'description' => '歌舞伎町の夜に浮かぶ月。キャストへのギフトはこちら。', 'image_color' => '#7c3aed',
                'prefecture' => '東京都', 'city' => '新宿区', 'address' => '歌舞伎町2-0-2 ノワールタワー7F', 'tel' => '03-0000-0012', 'business_license_number' => '新保第001号（架空）',
                'representative_name' => '黒崎 ゆあ', 'representative_tel' => '090-0000-0012',
                'bank_name' => 'みなと銀行', 'bank_branch' => '歌舞伎町支店', 'bank_account_type' => '普通', 'bank_account_number' => '5556667', 'bank_account_name' => 'クロサキ ユア',
            ],
            'menus' => [1, 3, 4, 7],   // 商品テンプレートの番号
            'industry' => 'ナイトワーク',
            // small-3。「贈る」は B（ギフトを贈る）
            'richmenu' => ['title' => 'ルナノワールメニュー', 'template' => 'small-3', 'image' => 'lunanoir-menu.png', 'actions' => [
                ['type' => 'text', 'value' => 'キャスト', 'label' => 'キャスト'],
                ['type' => 'none', 'value' => '', 'label' => 'ギフトを贈る'],
                ['type' => 'text', 'value' => '予約したい', 'label' => 'ご予約'],
            ]],
        ],
        'club-arcadia' => [
            'label' => 'クラブ アルカディア（ホストクラブ・大阪 ミナミ）',
            'slug' => 'club-arcadia',   // 課題で決める slug
            'store' => [
                'name' => 'クラブ アルカディア', 'description' => 'ミナミのホストクラブ。推しへのシャンパンはここから。', 'image_color' => '#ca8a04',
                'prefecture' => '大阪府', 'city' => '大阪市中央区', 'address' => '宗右衛門町0-1 アルカディア会館4F', 'tel' => '06-0000-0021', 'business_license_number' => '大保第000号（架空）',
                'representative_name' => '神崎 れお', 'representative_tel' => '090-0000-0021',
                'bank_name' => 'みなと銀行', 'bank_branch' => '心斎橋支店', 'bank_account_type' => '普通', 'bank_account_number' => '6667778', 'bank_account_name' => 'カンザキ レオ',
            ],
            'menus' => [3, 1, 8, 7],   // 商品テンプレートの番号
            'industry' => 'ナイトワーク',
            // large-4。「贈る」は D（推しに贈る）
            'richmenu' => ['title' => 'アルカディアメニュー', 'template' => 'large-4', 'image' => 'arcadia-menu.png', 'actions' => [
                ['type' => 'text', 'value' => 'ホスト紹介', 'label' => 'ホスト紹介'],
                ['type' => 'text', 'value' => '営業時間', 'label' => '営業時間'],
                ['type' => 'text', 'value' => 'お店の情報', 'label' => 'お店の情報'],
                ['type' => 'none', 'value' => '', 'label' => '推しに贈る'],
            ]],
        ],
        'club-soten' => [
            'label' => 'クラブ 蒼天（ホストクラブ・大阪 ミナミ）',
            'slug' => 'club-soten',   // 課題で決める slug
            'store' => [
                'name' => 'クラブ 蒼天', 'description' => 'ミナミのホストクラブ「蒼天」。記念日のシャンパンに。', 'image_color' => '#2563eb',
                'prefecture' => '大阪府', 'city' => '大阪市中央区', 'address' => '東心斎橋0-2 蒼天ビル6F', 'tel' => '06-0000-0022', 'business_license_number' => '大保第001号（架空）',
                'representative_name' => '天野 そら', 'representative_tel' => '090-0000-0022',
                'bank_name' => 'みなと銀行', 'bank_branch' => 'なんば支店', 'bank_account_type' => '普通', 'bank_account_number' => '7778889', 'bank_account_name' => 'アマノ ソラ',
            ],
            'menus' => [2, 1, 8, 7],   // 商品テンプレートの番号
            'industry' => 'ナイトワーク',
            // large-6。「贈る」は F（シャンパンを贈る）
            'richmenu' => ['title' => '蒼天メニュー', 'template' => 'large-6', 'image' => 'soten-menu.png', 'actions' => [
                ['type' => 'text', 'value' => 'ホスト紹介', 'label' => 'ホスト紹介'],
                ['type' => 'text', 'value' => '料金システム', 'label' => '料金システム'],
                ['type' => 'text', 'value' => '営業時間', 'label' => '営業時間'],
                ['type' => 'link', 'value' => 'https://www.instagram.com/', 'label' => 'Instagram'],
                ['type' => 'text', 'value' => 'お店の情報', 'label' => 'お店の情報'],
                ['type' => 'none', 'value' => '', 'label' => 'シャンパンを贈る'],
            ]],
        ],
        'bar-tsukikage' => [
            'label' => 'BAR 月影（バー・京都 祇園）',
            'slug' => 'bar-tsukikage',   // 課題で決める slug
            'store' => [
                'name' => 'BAR 月影', 'description' => '祇園の路地裏のバー。バーテンダーに一杯を。', 'image_color' => '#9f1239',
                'prefecture' => '京都府', 'city' => '京都市東山区', 'address' => '祇園町南側0-3', 'tel' => '075-000-0031', 'business_license_number' => '京保第000号（架空）',
                'representative_name' => '月岡 しずく', 'representative_tel' => '090-0000-0031',
                'bank_name' => 'みなと銀行', 'bank_branch' => '祇園支店', 'bank_account_type' => '普通', 'bank_account_number' => '8889990', 'bank_account_name' => 'ツキオカ シズク',
            ],
            'menus' => [4, 6, 7],   // 商品テンプレートの番号
            'industry' => '飲食店',
            // large-3。「贈る」は B（ギフトを贈る）
            'richmenu' => ['title' => '月影メニュー', 'template' => 'large-3', 'image' => 'tsukikage-menu.png', 'actions' => [
                ['type' => 'text', 'value' => 'お店の情報', 'label' => 'お店の情報'],
                ['type' => 'none', 'value' => '', 'label' => 'ギフトを贈る'],
                ['type' => 'text', 'value' => '予約したい', 'label' => 'ご予約'],
            ]],
        ],
        'bar-akari' => [
            'label' => 'BAR 灯（バー・京都 先斗町）',
            'slug' => 'bar-akari',   // 課題で決める slug
            'store' => [
                'name' => 'BAR 灯', 'description' => '先斗町、鴨川沿いの小さなバー。灯りの下で乾杯を。', 'image_color' => '#15803d',
                'prefecture' => '京都府', 'city' => '京都市中京区', 'address' => '先斗町通0-4', 'tel' => '075-000-0032', 'business_license_number' => '京保第001号（架空）',
                'representative_name' => '灯 はるか', 'representative_tel' => '090-0000-0032',
                'bank_name' => 'みなと銀行', 'bank_branch' => '四条支店', 'bank_account_type' => '普通', 'bank_account_number' => '9990001', 'bank_account_name' => 'アカリ ハルカ',
            ],
            'menus' => [4, 5, 7],   // 商品テンプレートの番号
            'industry' => '飲食店',
            // large-4。「贈る」は B（一杯を贈る）
            'richmenu' => ['title' => '灯メニュー', 'template' => 'large-4', 'image' => 'akari-menu.png', 'actions' => [
                ['type' => 'text', 'value' => 'お店の情報', 'label' => 'お店の情報'],
                ['type' => 'none', 'value' => '', 'label' => '一杯を贈る'],
                ['type' => 'text', 'value' => '営業時間', 'label' => '営業時間'],
                ['type' => 'link', 'value' => 'https://www.instagram.com/', 'label' => 'Instagram'],
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

    // プルダウンで選んだとき：まだ作っていなければスタート地点を作る。作ってあれば、そのまま続きから
    public static function start(string $key): Store
    {
        $store = Store::find(self::storeId($key));
        return $store ?: self::reset($key);
    }

    // 構築履歴をリセット：課題のお店を、構築したものもふくめてすべて消す（お客さんのスマホからも消える）
    public static function resetAll(): void
    {
        foreach (array_keys(self::SCENARIOS) as $key) {
            self::destroy($key);
            Setting::whereIn('key', ["scenario.{$key}.store", "scenario.{$key}.oa"])->delete();
        }
        Inside::info('app', '構築履歴をリセットしました', '課題のお店（公式LINE・チャネル・LIFF・注文）をすべて消しました');
    }

    // この公式アカウントが課題のお店のものなら、そのお店
    public static function storeOfOa(int $oaId): ?Store
    {
        foreach (array_keys(self::SCENARIOS) as $k) {
            if ((int) Setting::get("scenario.{$k}.oa") === $oaId) return Store::find(self::storeId($k));
        }
        return null;
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
