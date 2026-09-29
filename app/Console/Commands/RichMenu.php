<?php

namespace App\Console\Commands;

use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

// =====================================================
// コースB：リッチメニューを Messaging API で作る（本物のLINE用）
//   php artisan lab:richmenu list --store=my-shop
//   php artisan lab:richmenu create richmenus/six.json richmenus/images/six.png --store=my-shop
//   php artisan lab:richmenu default {richMenuId} --store=my-shop
//   php artisan lab:richmenu setup-tabs --store=my-shop
//   php artisan lab:richmenu link {userId} {richMenuId} --store=my-shop
//   php artisan lab:richmenu delete {richMenuId} / delete-all --store=my-shop
// トークンは --store の店舗（管理画面に入れた長期トークン）を使います。
// JSON の中の {LIFF_ID} は、その店舗の LIFF ID に置き換わります。
// =====================================================
class RichMenu extends Command
{
    protected $signature = 'lab:richmenu {action} {args?*} {--store= : 店舗の slug}';
    protected $description = '（コースB）リッチメニューを Messaging API で作る';

    private string $token = '';
    private string $liffId = '';

    public function handle(): int
    {
        $store = Store::where('slug', (string) $this->option('store'))->first();
        if (! $store || ! $store->line_messaging_channel_access_token) {
            $this->error('--store=slug で、長期トークンを入れた店舗を指定してください。');
            return self::FAILURE;
        }
        $this->token = $store->line_messaging_channel_access_token;
        $this->liffId = (string) $store->liff_id;
        $a = $this->argument('args');

        return match ($this->argument('action')) {
            'list' => $this->list(),
            'create' => $this->create($a[0] ?? '', $a[1] ?? '') ? self::SUCCESS : self::FAILURE,
            'default' => $this->api('POST', "/v2/bot/user/all/richmenu/{$a[0]}") ? $this->done("全員のデフォルトにしました: {$a[0]}") : self::FAILURE,
            'link' => $this->api('POST', "/v2/bot/user/{$a[0]}/richmenu/{$a[1]}") ? $this->done("{$a[0]} さんだけ {$a[1]} にしました") : self::FAILURE,
            'delete' => $this->api('DELETE', "/v2/bot/richmenu/{$a[0]}") ? $this->done("削除しました: {$a[0]}") : self::FAILURE,
            'delete-all' => $this->deleteAll(),
            'setup-tabs' => $this->setupTabs(),
            default => $this->done('action は list / create / default / link / delete / delete-all / setup-tabs'),
        };
    }

    private function done(string $m): int { $this->info('○ '.$m); return self::SUCCESS; }

    private function api(string $method, string $path, ?array $json = null, string $base = 'https://api.line.me'): array|false
    {
        $req = Http::withToken($this->token)->acceptJson();
        $res = $json === null ? $req->send($method, $base.$path) : $req->send($method, $base.$path, ['json' => $json]);
        if (! $res->successful()) {
            $this->error("× {$method} {$path} → {$res->status()} ".$res->body());
            if ($res->status() === 401) $this->line('  → トークンが無効です。Developers で再発行し、管理画面を更新しましょう。');
            return false;
        }
        return $res->json() ?? [];
    }

    private function create(string $jsonFile, string $image): ?string
    {
        $body = json_decode(str_replace('{LIFF_ID}', $this->liffId ?: 'LIFF_IDが未設定', file_get_contents(base_path($jsonFile))), true);
        // 1. 「どこを押すと何が起きるか」だけを先に登録（画像はまだ）
        if ($this->api('POST', '/v2/bot/richmenu/validate', $body) === false) return null;
        $res = $this->api('POST', '/v2/bot/richmenu', $body);
        if (! $res) return null;
        // 2. その richMenuId に画像をアップロード（送り先のドメインが api-data.line.me なのに注意）
        $up = Http::withToken($this->token)->withBody(file_get_contents(base_path($image)), str_ends_with($image, '.png') ? 'image/png' : 'image/jpeg')
            ->post("https://api-data.line.me/v2/bot/richmenu/{$res['richMenuId']}/content");
        if (! $up->successful()) { $this->error('× 画像のアップロード → '.$up->status().' '.$up->body()); return null; }
        $this->info("○ 作成しました: {$res['richMenuId']}（{$body['name']}）");
        return $res['richMenuId'];
    }

    private function list(): int
    {
        $list = $this->api('GET', '/v2/bot/richmenu/list');
        if ($list === false) return self::FAILURE;
        $default = Http::withToken($this->token)->get('https://api.line.me/v2/bot/user/all/richmenu')->json('richMenuId');
        if (! $list['richmenus']) $this->line('（APIで作ったリッチメニューはありません。Managerで作ったものはここに出ません）');
        foreach ($list['richmenus'] as $m) $this->line(($m['richMenuId'] === $default ? '★ ' : '  ')."{$m['richMenuId']}  {$m['name']}  {$m['size']['width']}x{$m['size']['height']}  ボタン".count($m['areas']).'個');
        foreach (($this->api('GET', '/v2/bot/richmenu/alias/list')['aliases'] ?? []) as $x) $this->line("   エイリアス {$x['richMenuAliasId']} → {$x['richMenuId']}");
        return self::SUCCESS;
    }

    private function alias(string $aliasId, string $menuId): void
    {
        $exists = collect($this->api('GET', '/v2/bot/richmenu/alias/list')['aliases'] ?? [])->contains('richMenuAliasId', $aliasId);
        $exists ? $this->api('POST', "/v2/bot/richmenu/alias/{$aliasId}", ['richMenuId' => $menuId])
            : $this->api('POST', '/v2/bot/richmenu/alias', ['richMenuAliasId' => $aliasId, 'richMenuId' => $menuId]);
        $this->info("○ エイリアス {$aliasId} → {$menuId}");
    }

    private function setupTabs(): int
    {
        $a = $this->create('richmenus/tab-a.json', 'richmenus/images/tab-a.png');
        $b = $this->create('richmenus/tab-b.json', 'richmenus/images/tab-b.png');
        if (! $a || ! $b) return self::FAILURE;
        $this->alias('tab-a', $a);
        $this->alias('tab-b', $b);
        $this->api('POST', "/v2/bot/user/all/richmenu/{$a}");
        return $this->done('タブAをデフォルトにしました。スマホでタブを押して切り替わるか試しましょう。');
    }

    private function deleteAll(): int
    {
        foreach (($this->api('GET', '/v2/bot/richmenu/alias/list')['aliases'] ?? []) as $x) $this->api('DELETE', "/v2/bot/richmenu/alias/{$x['richMenuAliasId']}");
        $list = $this->api('GET', '/v2/bot/richmenu/list')['richmenus'] ?? [];
        foreach ($list as $m) $this->api('DELETE', "/v2/bot/richmenu/{$m['richMenuId']}");
        return $this->done('APIで作ったリッチメニュー '.count($list).' 個とエイリアスを削除しました');
    }
}
