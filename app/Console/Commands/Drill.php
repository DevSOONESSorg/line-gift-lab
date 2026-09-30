<?php

namespace App\Console\Commands;

use App\Models\Menu;
use App\Models\Mock\Channel;
use App\Models\Mock\LiffApp;
use App\Models\Mock\OfficialAccount;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// =====================================================
// 障害ドリル（職員用）：設定をどこか1か所こっそり壊す
//   php artisan lab:drill            … 症状カードの一覧（答えは出ない）
//   php artisan lab:drill 3          … 3番を仕込む
//   php artisan lab:drill random
//   php artisan lab:drill 3 --store=club-azure
// 裏側ビューには記録を残しません。答えは docs/facilitator.md
// =====================================================
class Drill extends Command
{
    protected $signature = 'lab:drill {no? : 番号 または random} {--store= : 対象の slug}';
    protected $description = '（職員用）障害ドリルを仕込む';

    private const CARDS = [
        1 => 'トークで話しかけても、ギフトの案内が返ってこない（A）',
        2 => 'トークで話しかけても、ギフトの案内が返ってこない（B）',
        3 => '返事が2通来る',
        4 => 'トークで話しかけても、ギフトの案内が返ってこない（C）',
        5 => 'リッチメニューの「贈る」を押すと、ちがうお店が開く',
        6 => '「贈る」を押しても、ギフトを贈れない（A）',
        7 => '「贈る」を押しても、ギフトを贈れない（B）',
        8 => 'リッチメニューの「贈る」を押すとエラーになる',
        9 => 'ギフトを贈ったのに、お知らせがLINEに届かない',
        10 => 'トークで話しかけても、ギフトの案内が返ってこない（D）',
    ];

    public function handle(): int
    {
        $store = $this->option('store') ? Store::where('slug', $this->option('store'))->first()
            : Store::whereNotIn('slug', ['sample-bar', 'lumiere', 'store-pending'])->latest('id')->first();
        $no = $this->argument('no');

        if (! $no) {
            $this->line('障害ドリル一覧（参加者に渡す「症状カード」）');
            foreach (self::CARDS as $n => $c) $this->line(sprintf('  %2d. %s', $n, $c));
            $this->line('対象店舗: '.($store ? "{$store->name}（{$store->slug}）" : '見つかりません'));
            return self::SUCCESS;
        }
        if (! $store) { $this->error('対象の店舗が見つかりません。--store=slug で指定してください。'); return self::FAILURE; }
        $ch = Channel::byChannelId($store->line_messaging_channel_id);
        $oa = $ch?->officialAccount;
        if (! $ch || ! $oa) { $this->error("「{$store->name}」は疑似LINEの店舗専用チャネルとつながっていません。"); return self::FAILURE; }
        $n = $no === 'random' ? array_rand(self::CARDS) : (int) $no;

        match ($n) {
            // 1: 自社側のシークレットを1文字変える → 署名NG（401）
            1 => $store->update(['line_messaging_channel_secret' => substr($store->line_messaging_channel_secret, 0, -1).(str_ends_with($store->line_messaging_channel_secret, 'a') ? 'b' : 'a')]),
            // 2: LINE側でトークンを再発行（自社は古いまま） → 返信 401
            2 => $ch->update(['access_token' => bin2hex(random_bytes(8)).'/'.base64_encode(random_bytes(40))]),
            // 3: 応答メッセージ ON → 2通
            3 => $oa->update(['auto_reply_on' => true]),
            // 4: Webhook URL の slug を打ち間違える → 404
            4 => $ch->update(['webhook_url' => str_replace("/store/{$store->slug}", '/store/'.(str_contains($store->slug, '-') ? str_replace('-', '_', $store->slug) : $store->slug.'s'), $ch->webhook_url)]),
            // 5: LIFF のエンドポイントURL を別のお店に → ちがう店が開く
            5 => LiffApp::where('liff_id', $store->liff_id)->update(['endpoint_url' => DB::raw("replace(endpoint_url, '/liff/s/{$store->slug}', '/liff/s/sample-bar')")]),
            // 6: 承認を取り消す → 準備中
            6 => $store->update(['is_approved' => false]),
            // 7: 商品を全部「停止中」 → 商品がない
            7 => Menu::where('store_id', $store->id)->update(['is_active' => false]),
            // 8: リッチメニューの LIFF ID を1文字変える
            8 => $this->breakRichMenu($oa),
            // 9: スマホが店舗の公式アカウントをブロック → push が届かない
            9 => DB::connection('mockline')->table('friends')->where('official_account_id', $oa->id)->update(['blocked' => true]),
            // 10: 自動返信（ギフト誘導）OFF → bot が反応しない
            10 => $store->update(['auto_reply_enabled' => false]),
            default => $this->error('番号が正しくありません'),
        };
        $this->info("仕込みました。対象：「{$store->name}」");
        $this->line('参加者に渡す症状カード：「'.(self::CARDS[$n] ?? '?').'」');
        return self::SUCCESS;
    }

    private function breakRichMenu(OfficialAccount $oa): void
    {
        $rm = $oa->richMenus()->where('is_default', true)->first();
        if (! $rm) { $this->error('リッチメニューがありません'); return; }
        $rm->update(['actions' => collect($rm->actions)->map(fn ($a) => $a['type'] === 'link' ? ['type' => 'link', 'value' => substr($a['value'], 0, -1).(str_ends_with($a['value'], 'x') ? 'y' : 'x')] : $a)->all()]);
    }
}
