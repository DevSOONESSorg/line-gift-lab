<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Agent;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\MenuTemplate;
use App\Models\Mock\LineUser;
use App\Models\Mock\OfficialAccount;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\MockLine\MockLine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

// =====================================================
// 最初のデータ（php artisan migrate:fresh --seed で作り直せる）
//   ・管理画面のログインユーザー
//   ・商品テンプレート、代理店
//   ・運営の公式LINE「おくりギフト(dev)」（共通チャネル・LIFF・リッチメニュー）
//   ・見本のお店：見本バー（共通掲載）、見本カフェ（オリジナル：店舗専用LINE・全部つながった完成形）、承認待ちの店
//   ・いろいろな状態の注文（管理画面の練習用）
// =====================================================
class DatabaseSeeder extends Seeder
{
    private const LOCAL = 'http://localhost:';

    public function run(): void
    {
        $local = self::LOCAL.config('lab.host_port');

        // 前回のアップロード（お礼動画・リッチメニュー画像・身分証）を消してから作り直す
        Storage::deleteDirectory('thank-videos');
        Storage::deleteDirectory('id-documents');
        Storage::disk('public')->deleteDirectory('richmenus');

        User::create(['name' => '運営スタッフ', 'email' => 'admin@example.com', 'password' => Hash::make('Taiken-2026')]);

        // ---------- 商品テンプレート（ブランド名は使わず、体験用の名前） ----------
        $templates = [
            ['シャンパン（高級）', 'プレステージ・ブリュット', '重厚な味わいと繊細な泡の王道シャンパン。', 100000, '#1f2937'],
            ['シャンパン（高級）', 'プレステージ・ロゼ', '華やかな香りと奥深い味わいのロゼ。', 180000, '#be185d'],
            ['シャンパン（高級）', 'ゴールドボトル', 'ゴージャスな外観で特別なひとときを。', 103500, '#b45309'],
            ['シャンパン（スタンダード）', 'スタンダード・ブリュット', 'すっきりとした飲み口の定番シャンパン。', 14490, '#374151'],
            ['シャンパン（スタンダード）', 'スタンダード・ロゼ', '甘さとフルーティーさのバランスが絶妙。', 18630, '#db2777'],
            ['ワイン', 'ハウスワイン（ボトル）', 'お店おすすめの赤または白。', 5000, '#7f1d1d'],
            ['その他ドリンク', 'スタッフ乾杯用', 'スタッフのみんなで乾杯！', 1000, '#0e7490'],
            ['その他ドリンク', 'オリジナルシャンパンタワー', 'お店オリジナルの特別演出。', 100000, '#7c3aed'],
            ['フード', 'フルーツ盛り合わせ', '季節のフルーツを盛り合わせで。', 8000, '#ea580c'],
        ];
        foreach ($templates as $i => [$cat, $name, $desc, $price, $color]) {
            MenuTemplate::create(['sort_order' => $i + 1, 'category' => $cat, 'name' => $name, 'description' => $desc, 'reference_price' => $price, 'image_color' => $color]);
        }

        // ---------- 代理店 ----------
        $agent1 = Agent::create(['name' => 'うるま紹介センター', 'company' => '株式会社うるま（架空）', 'reward_rate' => 3, 'referral_code' => Agent::newReferralCode()]);
        $agent2 = Agent::create(['name' => '那覇サブエージェント', 'company' => '', 'parent_id' => $agent1->id, 'reward_rate' => 0, 'referral_code' => Agent::newReferralCode()]);

        // ---------- 疑似LINE：アカウント・プロバイダー・あなたのスマホ ----------
        $ml = DB::connection('mockline');
        $ml->table('accounts')->insert([
            ['id' => 'personal', 'name' => 'あなたの個人アカウント（オーナー役）', 'email' => 'you@example.com'],
            ['id' => 'company', 'name' => '会社の作業アカウント（support役）', 'email' => 'support@okuri-gift.example'],
        ]);
        $providerId = $ml->table('providers')->insertGetId(['name' => MockLine::PLATFORM_PROVIDER]);
        $ml->table('provider_members')->insert(['provider_id' => $providerId, 'account_id' => 'company', 'role' => 'admin']);
        // 疑似スマホは2台：お客さん（ギフトを贈る人）と、お店のオーナー（受け取る人）
        $guest = LineUser::create(['user_id' => 'U'.bin2hex(random_bytes(16)), 'display_name' => 'お客さん', 'phone' => 'customer']);
        $owner = LineUser::create(['user_id' => 'U'.bin2hex(random_bytes(16)), 'display_name' => 'オーナー', 'phone' => 'owner']);

        // ---------- 運営の公式LINE（共通チャネル） ----------
        $poa = MockLine::createOfficialAccount('おくりギフト(dev)', 'company', 'サービス', true);
        $poa->update(['auto_reply_on' => false]);
        $pch = MockLine::enableMessagingApi($poa, $providerId, 'company');
        $ptoken = MockLine::issueToken($pch);
        $pch->update(['webhook_url' => "{$local}/api/webhook/line/platform", 'use_webhook' => true]);
        $plogin = MockLine::createLoginChannel($providerId, 'おくりギフト(dev) 共通アプリ', 'company');
        $plogin->update(['is_published' => true]);
        // サイズ：お客さん向けは Tall（トークの上に下から出る）、オーナー向けは Full（全画面）
        $liff = fn ($name, $path, $size = 'Tall') => MockLine::addLiff($plogin, $name, "{$local}/liff/{$path}", $size, 'profile openid chat_message.write', true);
        $lShops = $liff('お店をさがす', 'shops');
        $lHistory = $liff('送信履歴', 'history');
        $lThanks = $liff('お礼一覧', 'thanks');
        $lRegister = $liff('出店登録', 'register', 'Full');
        $lManage = $liff('店舗管理', 'manage', 'Full');
        $this->richMenu($poa, '共通メニュー', 'large-6', 'platform-menu.png', [
            ['type' => 'link', 'value' => "https://liff.line.me/{$lShops->liff_id}"],
            ['type' => 'link', 'value' => "https://liff.line.me/{$lHistory->liff_id}"],
            ['type' => 'link', 'value' => "https://liff.line.me/{$lThanks->liff_id}"],
            ['type' => 'link', 'value' => "https://liff.line.me/{$lRegister->liff_id}"],
            ['type' => 'link', 'value' => "https://liff.line.me/{$lManage->liff_id}"],
            ['type' => 'text', 'value' => '使い方'],
        ]);
        foreach ([
            'platform_channel_id' => $pch->channel_id, 'platform_secret' => $pch->secret, 'platform_token' => $ptoken,
            'platform_basic_id' => $poa->basic_id, 'platform_liff_thanks' => $lThanks->liff_id, 'platform_liff_manage' => $lManage->liff_id,
        ] as $k => $v) Setting::put($k, $v);

        // ---------- 見本バー（共通掲載。店舗専用LINEはなし） ----------
        $bar = Store::create([
            'name' => '見本バー', 'slug' => 'sample-bar', 'description' => '共通アプリに掲載している見本のお店です。', 'image_color' => '#7c3aed',
            'prefecture' => '沖縄県', 'city' => '那覇市', 'address' => '体験町1-1', 'tel' => '098-000-0001',
            'representative_name' => '見本 花子', 'representative_tel' => '090-0000-0001',
            'bank_name' => 'さくら銀行', 'bank_branch' => '那覇支店', 'bank_account_type' => '普通', 'bank_account_number' => '1234567', 'bank_account_name' => 'ミホン ハナコ',
            'agent_id' => $agent2->id, 'commission_rate' => 13.6, 'is_approved' => true, 'is_listed_in_directory' => true,
            'owner_line_user_id' => $owner->user_id,
        ]);
        foreach ([6, 7, 4, 1] as $tid) {
            $t = MenuTemplate::find($tid);
            $bar->menus()->create(['menu_template_id' => $t->id, 'name' => $t->name, 'price' => $t->reference_price]);
        }
        // 来月だけ手数料率を変える例
        $bar->monthlyRates()->create(['year' => now()->addMonth()->year, 'month' => now()->addMonth()->month, 'rate' => 10]);

        // ---------- 見本カフェ（オリジナル：店舗専用の公式LINE。手順書どおりに全部つながった完成形） ----------
        // 本番と同じ流れ：個人アカウントで作成 → 会社アカウントを運用担当者に → 会社アカウントで Messaging API 有効化
        $soa = MockLine::createOfficialAccount('見本カフェ', 'personal', '飲食');
        $ml->table('oa_members')->insert(['official_account_id' => $soa->id, 'account_id' => 'company', 'role' => 'operator']);
        $soa->update(['auto_reply_on' => false]);
        $sch = MockLine::enableMessagingApi($soa, $providerId, 'company');
        $stoken = MockLine::issueToken($sch);
        $sch->update(['webhook_url' => "{$local}/api/webhook/line/store/sample-cafe", 'use_webhook' => true]);
        $slogin = MockLine::createLoginChannel($providerId, '見本カフェ LIFF', 'company');
        $slogin->update(['is_published' => true]);
        $sliff = MockLine::addLiff($slogin, '見本カフェでギフトを贈る', "{$local}/liff/s/sample-cafe", 'Tall', 'profile openid chat_message.write', true);
        // お店の公式LINEのメニュー：店舗ページ（このお店の LIFF）／送信履歴・お礼一覧（運営の LIFF）
        $this->richMenu($soa, '店舗メニュー', 'small-3', 'cafe-menu.png', [
            ['type' => 'link', 'value' => "https://liff.line.me/{$sliff->liff_id}"],
            ['type' => 'link', 'value' => "https://liff.line.me/{$lHistory->liff_id}"],
            ['type' => 'link', 'value' => "https://liff.line.me/{$lThanks->liff_id}"],
        ]);
        $cafe = Store::create([
            'name' => '見本カフェ', 'slug' => 'sample-cafe', 'description' => '店舗専用の公式LINEから贈れる見本のお店です。', 'image_color' => '#e8590c',
            'prefecture' => '沖縄県', 'city' => '浦添市', 'tel' => '098-000-0002',
            'representative_name' => '見本 太郎', 'representative_tel' => '090-0000-0002',
            'bank_name' => 'ゆうちょ銀行', 'bank_account_type' => '普通', 'bank_account_name' => 'ミホン タロウ', 'yucho_symbol' => '17010', 'yucho_number' => '12345671',
            'agent_id' => $agent1->id, 'commission_rate' => 10, 'is_approved' => true, 'is_listed_in_directory' => false, 'wants_original' => true,
            'owner_line_user_id' => $owner->user_id,
            'liff_id' => $sliff->liff_id, 'line_messaging_channel_id' => $sch->channel_id, 'line_messaging_channel_secret' => $sch->secret,
            'line_messaging_channel_access_token' => $stoken, 'line_official_account_id' => $soa->basic_id,
        ]);
        foreach ([['スタッフ乾杯用', 1000], ['ケーキセット', 1500], ['スタンダード・ブリュット', 14490]] as [$n, $p]) $cafe->menus()->create(['name' => $n, 'price' => $p]);

        // ---------- 承認待ちのお店（承認の練習用） ----------
        Store::create(['name' => '承認待ちスナック', 'slug' => 'store-pending', 'prefecture' => '沖縄県', 'city' => '沖縄市', 'tel' => '098-000-0003',
            'representative_name' => '申込 次郎', 'representative_tel' => '090-0000-0003', 'commission_rate' => 10, 'is_approved' => false]);

        // お客さんのスマホは、最初から運営LINEと見本カフェの友だち
        // オーナーのスマホは、運営LINEの友だち（出店登録・店舗管理・ギフトのお知らせは運営LINEから）
        foreach ([[$guest, $poa], [$guest, $soa], [$owner, $poa]] as [$user, $oa]) {
            $ml->table('friends')->insert(['official_account_id' => $oa->id, 'user_id' => $user->user_id, 'blocked' => false]);
            MockLine::addMessage($oa->id, $user->user_id, 'out', 'greeting', $oa->greeting_text);
        }

        $this->sampleOrders($bar, $cafe);

        DB::connection('inside')->table('logs')->delete();   // 初期データづくりの記録は消しておく
        \App\Support\Inside::info('app', '初期データを作りました（運営LINE・見本バー・見本カフェ・承認待ちの店・練習用の注文）');
    }

    private function richMenu(OfficialAccount $oa, string $title, string $template, string $image, array $actions): void
    {
        $path = "richmenus/seed-{$oa->id}.png";
        Storage::disk('public')->put($path, file_get_contents(database_path("seeders/images/{$image}")));
        [$w, $h] = getimagesize(database_path("seeders/images/{$image}"));
        $oa->richMenus()->create(['title' => $title, 'template' => $template, 'image_path' => $path, 'width' => $w, 'height' => $h, 'actions' => $actions, 'is_default' => true]);
    }

    // いろいろな状態の注文（架空のお客さん）
    private function sampleOrders(Store $bar, Store $cafe): void
    {
        $names = ['ゆう', 'たろう', 'はなこ', 'ケン', 'みさき'];
        $customers = collect($names)->map(fn ($n, $i) => Customer::create([
            'line_user_id' => 'U'.bin2hex(random_bytes(16)), 'line_display_name' => $n, 'name' => $i % 2 ? null : "{$n}（架空）",
            'card_last4' => '4242', 'card_exp' => '12/40',
        ]));
        $plan = [
            // [店, 何日前, 状態, 支払い, 経路]
            [$bar, 40, OrderStatus::Thanked, PaymentMethod::Card, 'common'],
            [$bar, 35, OrderStatus::Received, PaymentMethod::Card, 'common'],
            [$cafe, 33, OrderStatus::Thanked, PaymentMethod::Card, 'original'],
            [$bar, 20, OrderStatus::Expired, PaymentMethod::Card, 'common'],
            [$cafe, 12, OrderStatus::Refunded, PaymentMethod::Card, 'original'],
            [$bar, 6, OrderStatus::Cancelled, PaymentMethod::BankTransfer, 'common'],
            [$bar, 3, OrderStatus::Thanked, PaymentMethod::BankTransfer, 'common'],
            [$cafe, 2, OrderStatus::Received, PaymentMethod::Card, 'original'],
            [$bar, 1, OrderStatus::AwaitingPayment, PaymentMethod::BankTransfer, 'common'],
            [$cafe, 0, OrderStatus::Requested, PaymentMethod::Card, 'original'],
        ];
        foreach ($plan as $i => [$store, $days, $status, $method, $route]) {
            $menu = $store->menus()->inRandomOrder()->first();
            $at = now()->subDays($days)->setTime(20 + $i % 3, 10 * ($i % 6));
            if ($at->isFuture()) $at = now()->subMinutes(30 + 10 * $i);   // 夜より前に作り直したとき、未来の日時にしない
            $rate = CommissionService::rateFor($store, $at);
            $o = Order::create([
                'store_id' => $store->id, 'menu_id' => $menu->id, 'customer_id' => $customers[$i % 5]->id, 'menu_name' => $menu->name, 'amount' => $menu->price,
                'commission_rate' => $rate, 'commission' => CommissionService::commission($menu->price, $rate), 'payment_method' => $method,
                'status' => $status, 'route' => $route, 'message' => $i % 2 ? "いつもありがとう！\nみんなで飲んでね" : null,
                'sender_name' => $customers[$i % 5]->line_display_name, 'recipient_name' => $i % 3 === 0 ? 'スタッフ あや' : null, 'card_last4' => $method === PaymentMethod::Card ? '4242' : null,
                'expires_at' => in_array($status, [OrderStatus::Requested, OrderStatus::AwaitingPayment]) ? $at->copy()->addDays(3) : null,
                'paid_at' => in_array($status, [OrderStatus::Received, OrderStatus::Thanked, OrderStatus::Refunded]) ? $at : null,
                'received_at' => in_array($status, [OrderStatus::Received, OrderStatus::Thanked, OrderStatus::Refunded]) ? $at->copy()->addHours(2) : null,
                'thanked_at' => $status === OrderStatus::Thanked ? $at->copy()->addHours(3) : null,
                'thank_message' => $status === OrderStatus::Thanked ? 'ありがとうございます！みんなでいただきました！' : null,
                'refunded_at' => $status === OrderStatus::Refunded ? $at->copy()->addDay() : null,
                'cancelled_at' => $status === OrderStatus::Cancelled ? $at->copy()->addDays(3) : null,
                'created_at' => $at, 'updated_at' => $at,
            ]);
            $o->statusLogs()->create(['from' => null, 'to' => $status->value, 'by' => 'system', 'note' => '練習用の初期データ', 'created_at' => $at]);
            if ($status === OrderStatus::Thanked) {
                $path = "thank-videos/{$o->id}/sample-thanks.mp4";
                Storage::put($path, file_get_contents(database_path('seeders/videos/sample-thanks.mp4')));
                $o->update(['thank_video_path' => $path]);
            }
        }
    }
}
