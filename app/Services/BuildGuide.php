<?php

namespace App\Services;

use App\Models\Mock\Channel;
use App\Models\Mock\LiffApp;
use App\Models\Mock\OfficialAccount;
use App\Models\Mock\Provider;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Store;
use App\Services\MockLine\MockLine;
use Illuminate\Support\Facades\DB;

// =====================================================
// 構築ナビ：お店の公式LINEと自社サービスを、本番と同じ順番でつなぐための道案内
//
// ★ このナビのルール（手順や項目が増えても、必ず守る）
//   1. 各手順は「何を開く → 何を押す／選ぶ → 何をコピーして → どこに貼る → 何を確認する」を1行ずつ書く（actions）
//   2. 「終わったか」は入力ではなく、実際の設定（疑似LINE・自社DB）を見て判定する（done）
//   3. 手順は飛ばせない。前の手順が終わるまで、後ろの手順はグレーアウト（中身もリンクも出さない）
//      → steps() の最後で、上から順に「完了 → いまここ → ロック」を決めている
//   4. 手順が終わったら「完了」を表示し、ナビの一覧にチェックを付けて、次の手順を開く
//
// actions の書き方： [種類, 説明, 値(任意), コピー元のURL(任意)]
//   open   … 画面を開く（3つ目にURL）           click … ボタンを押す
//   select … 選ぶ・チェックする・スイッチを切り替える
//   copy   … コピーする                         paste … 貼り付ける／入力する（値があればコピーボタンを出す）
//   switch … 「ログイン中」を切り替える（3つ目にアカウント）   check … 確認する
// =====================================================
class BuildGuide
{
    public const ACCOUNTS = ['personal' => 'オーナー（個人アカウント）', 'company' => '会社の作業アカウント', 'admin' => '運営スタッフ（管理画面）', 'phone' => 'お客さん・オーナーのスマホ'];

    public const ACTION_LABELS = ['open' => '開く', 'click' => '押す', 'select' => '選ぶ', 'copy' => 'コピー', 'paste' => '貼り付け', 'switch' => '切り替え', 'check' => '確認'];

    public ?OfficialAccount $oa = null;
    public ?Channel $messaging = null;
    public ?Channel $login = null;
    public ?LiffApp $liff = null;
    public ?string $scenario = null;

    public function __construct(public Store $store)
    {
        $this->scenario = BuildScenario::keyOf($store);
        $this->oa = OfficialAccount::byBasicId($store->line_official_account_id)
            ?? ($this->scenario ? OfficialAccount::find(Setting::get("scenario.{$this->scenario}.oa")) : null)
            ?? OfficialAccount::where('name', $store->name)->where('is_platform', false)->latest('id')->first();
        $this->messaging = $this->oa?->messagingChannel;
        $this->liff = LiffApp::where('endpoint_url', 'like', '%/liff/s/'.$store->slug)->orderByDesc('liff_id')->first();
        $this->login = $this->liff?->channel
            ?? Channel::find(session("build.login.{$store->id}"))
            ?? Channel::where('type', 'login')->whereNull('official_account_id')->where('name', 'like', $store->name.'%')->latest('id')->first();
    }

    public static function current(): ?self
    {
        $store = Store::find(session('build.store'));
        return $store ? new self($store) : null;
    }

    public function targetSlug(): string { return $this->scenario ? BuildScenario::SCENARIOS[$this->scenario]['slug'] : $this->store->slug; }
    public function liffUrl(): string { return rtrim(PublicUrl::local(), '/').'/liff/s/'.$this->targetSlug(); }
    public function webhookUrl(): string { return rtrim(PublicUrl::local(), '/').'/api/webhook/line/store/'.$this->targetSlug(); }

    /** @return array<int, array> */
    public function steps(): array
    {
        $s = $this->store; $oa = $this->oa; $ch = $this->messaging; $login = $this->login; $liff = $this->liff;
        $platform = Provider::where('name', MockLine::PLATFORM_PROVIDER)->first();
        $slug = $this->targetSlug();
        $inviteOpen = $oa && DB::connection('mockline')->table('invites')->where('official_account_id', $oa->id)->whereNull('used_by')->exists();

        $steps = [
            [
                'key' => 'approve', 'title' => '出店登録を確認して承認し、slug を決める', 'who' => 'admin', 'chapter' => '01-store',
                'done' => $s->is_approved && $s->slug === $slug,
                'actions' => [
                    ['open', '管理画面の店舗編集を開く', route('admin.stores.edit', $s)],
                    ['check', '「代表者情報」「振込先情報」に値が入っているか見る'],
                    ['paste', '「スラッグ」欄の store-○ を消して、これを入力', $slug],
                    ['select', '「管理設定」の「承認済み」にチェック'],
                    ['click', 'ページ一番下の「更新」'],
                ],
                'why' => 'slug は LIFF と Webhook の URL に入る「お店の住所」。あとで変えると LINE 側の設定もすべて直すことになるので、最初に決めます。',
            ],
            [
                'key' => 'invite', 'title' => 'お店の公式LINEに、会社のアカウントを招待してもらう', 'who' => $inviteOpen ? 'company' : 'personal', 'chapter' => '03-official-account',
                'done' => $oa && $oa->roleOf('company'),
                'actions' => $inviteOpen ? [
                    ['check', '招待URLは発行ずみ。ここからは会社側の作業'],
                    ['switch', '「ログイン中」を会社の作業アカウントに', 'company'],
                    ['paste', 'さっきコピーした招待URLを、ブラウザのアドレス欄に貼って開く'],
                    ['click', '「参加する」'],
                ] : [
                    ['switch', '「ログイン中」をオーナー（個人アカウント）に', 'personal'],
                    ['open', 'Manager の「設定 › 権限管理」を開く', $oa ? route('mock.manager.oa.members', $oa) : route('mock.manager')],
                    ['select', '「メンバーを追加」の権限で「運用担当者」を選ぶ'],
                    ['click', '「URLを発行」'],
                    ['copy', '出てきた招待URLの横の「コピー」'],
                    ['check', '本番では、ここまでをお店のオーナーにお願いして、URLを送ってもらいます'],
                ],
                'why' => 'お店の公式LINEの持ち主はオーナーのまま。運営はパスワードを聞かず、招待で「作業する人」に入れてもらいます。',
            ],
            [
                'key' => 'messaging', 'title' => 'Messaging API を有効にする（運営のプロバイダー）', 'who' => 'company', 'chapter' => '03-official-account',
                'done' => $ch && $platform && $ch->provider_id == $platform->id,
                'bad' => $ch && $platform && $ch->provider_id != $platform->id ? '運営以外のプロバイダーで有効になっています。プロバイダーはあとから変えられないので、構築ナビでお店を選び直して最初からやり直してください。' : null,
                'actions' => [
                    ['switch', '「ログイン中」を会社の作業アカウントに', 'company'],
                    ['open', 'Manager の「設定 › Messaging API」を開く', $oa ? route('mock.manager.oa.messaging', $oa) : null],
                    ['check', '① 開発者情報はそのままでよい'],
                    ['select', '② プロバイダーで「'.MockLine::PLATFORM_PROVIDER.'」を選ぶ'],
                    ['click', '「Messaging APIを利用する」→ 確認で「OK」'],
                    ['check', 'Channel ID と Channel secret が表示されたら完了'],
                ],
                'why' => 'プロバイダーは「会社の箱」。運営の箱に入れると、お客さんのユーザーIDが運営のサービス全体でそろいます。あとから変えられません。',
            ],
            [
                'key' => 'login', 'title' => 'LINEログインチャネルを作って「公開」にする', 'who' => 'company', 'chapter' => '04-liff',
                'done' => $login && $login->is_published,
                'actions' => $login ? [
                    ['check', 'チャネルはできています。あとは公開するだけ'],
                    ['open', 'LINEログインチャネルの「チャネル基本設定」を開く', route('mock.developers.channel.show', $login)],
                    ['click', '「チャネルの状態」の「公開」'],
                    ['check', '「公開済み」になったら完了'],
                ] : [
                    ['open', 'LINE Developers の新規チャネル作成を開く', $platform ? route('mock.developers.provider', [$platform, 'create' => 'login']) : route('mock.developers')],
                    ['check', '種類は「LINEログイン」（Messaging API は選べない）'],
                    ['paste', '「チャネル名」にこれを入力', $s->name.' LIFF'],
                    ['select', 'アプリタイプ「ウェブアプリ」と「開発者契約に同意」にチェック'],
                    ['click', '「作成」'],
                ],
                'why' => 'LIFF は Messaging API チャネルには追加できず、LINEログインチャネルに追加します。「開発中」のままだと、お客さんは LIFF を開けません。',
            ],
            [
                'key' => 'liff', 'title' => 'LIFF アプリを追加する（贈る画面の入口）', 'who' => 'company', 'chapter' => '04-liff',
                'done' => (bool) $liff,
                'actions' => [
                    ['open', 'LINEログインチャネルの「LIFF」タブの追加画面を開く', $login ? route('mock.developers.channel.show', [$login, 'tab' => 'liff', 'add' => 1]) : null],
                    ['select', 'サイズは「Tall」'],
                    ['paste', '「エンドポイントURL」にこれを貼る', $this->liffUrl()],
                    ['check', 'Scope（openid・profile・chat_message.write）と友だち追加オプション On (normal) は、最初からそうなっている'],
                    ['click', '「追加」'],
                    ['check', '表に LIFF ID と LIFF URL が出たら完了（あとの手順で使う）'],
                ],
                'why' => 'LIFF ID が「LINEの中で開くページ（贈る画面）」の入口番号です。エンドポイントURL の slug が違うと、別のお店が開きます。',
            ],
            [
                'key' => 'token', 'title' => 'チャネルアクセストークン（長期）を発行する', 'who' => 'company', 'chapter' => '05-five-values',
                'done' => $ch && $ch->access_token,
                'actions' => [
                    ['open', 'Messaging API チャネルの「Messaging API設定」タブを開く', $ch ? route('mock.developers.channel.show', [$ch, 'tab' => 'messaging']) : null],
                    ['click', 'いちばん下「チャネルアクセストークン（長期）」の「発行」'],
                    ['check', '長い文字列が出たら完了（人に見せない・チャットに貼らない）'],
                ],
                'why' => '自社サーバーが LINE に「このお客さんに送って」とお願いするときの許可証です。',
            ],
            [
                'key' => 'values', 'title' => '管理画面に5つの値を入れる', 'who' => 'admin', 'chapter' => '05-five-values',
                'done' => $ch && $liff && $oa && $s->liff_id === $liff->liff_id && $s->line_messaging_channel_id === $ch->channel_id
                    && $s->line_messaging_channel_secret === $ch->secret && $s->line_messaging_channel_access_token === $ch->access_token
                    && mb_strtolower((string) $s->line_official_account_id) === mb_strtolower($oa->basic_id),
                'actions' => [
                    ['open', '管理画面の店舗編集「LINE 連携設定」を開く（コピー元とは別のタブで開くと楽）', route('admin.stores.edit', $s).'#line'],
                    ['paste', '「LIFF ID」← LINEログインチャネルの LIFF タブ', $liff?->liff_id, $login ? route('mock.developers.channel.show', [$login, 'tab' => 'liff']) : null],
                    ['paste', '「Messaging API チャネルID」← Messaging API チャネルの「チャネル基本設定」', $ch?->channel_id, $ch ? route('mock.developers.channel.show', $ch) : null],
                    ['paste', '「チャネルシークレット」← 同じ「チャネル基本設定」（秘密なので、ここには表示しません）', null, $ch ? route('mock.developers.channel.show', $ch) : null],
                    ['paste', '「アクセストークン（長期）」←「Messaging API設定」のいちばん下（秘密なので、ここには表示しません）', null, $ch ? route('mock.developers.channel.show', [$ch, 'tab' => 'messaging']) : null],
                    ['paste', '「LINE公式アカウントID」← Manager の「アカウント設定」のベーシックID', $oa?->basic_id, $oa ? route('mock.manager.oa.settings', $oa) : null],
                    ['click', 'いちばん下の「更新」→ 下の「設定整合性」の「チェックする」'],
                    ['check', 'すべて OK になったら完了'],
                ],
                'why' => 'この5つで、自社のお店と LINE 側の公式アカウント・チャネル・LIFF が結びつきます。1つでも別のお店の値が混ざると、つながりません。',
            ],
            [
                'key' => 'webhook', 'title' => 'Webhook URL を登録して「検証」する', 'who' => 'company', 'chapter' => '06-webhook',
                'done' => $ch && str_ends_with($ch->webhook_url, '/api/webhook/line/store/'.$slug) && $ch->webhook_verified_at,
                'actions' => [
                    ['open', 'Messaging API チャネルの「Messaging API設定」タブを開く', $ch ? route('mock.developers.channel.show', [$ch, 'tab' => 'messaging']) : null],
                    ['click', '「Webhook URL」の「編集」'],
                    ['paste', 'このURLを貼る', $this->webhookUrl()],
                    ['click', '「更新」→「検証」'],
                    ['check', '「成功（200）」が出たら完了'],
                ],
                'why' => 'お客さんがトークで話しかけたとき、LINE社がこのURLに知らせてくれます。検証は、URL と署名（シークレット）が合っているかの確認です。',
            ],
            [
                'key' => 'response', 'title' => '応答設定：Webhook をオン、応答メッセージをオフ', 'who' => 'company', 'chapter' => '07-response',
                'done' => $ch && $ch->use_webhook && $oa && ! $oa->auto_reply_on && $oa->response_mode === 'bot',
                'actions' => [
                    ['open', 'Manager の「設定 › 応答設定」を開く', $oa ? route('mock.manager.oa.response', $oa) : null],
                    ['select', '「チャット」をオフ'],
                    ['select', '「応答メッセージ」をオフ'],
                    ['select', '「Webhook」をオン'],
                    ['click', '「保存」'],
                ],
                'why' => '応答メッセージがオンのままだと、LINE社の定型文と自社の案内の2通が返ります。',
            ],
            [
                'key' => 'richmenu', 'title' => 'リッチメニューの「ギフトを贈る」に、LIFF の URL を入れる', 'who' => 'company', 'chapter' => '08-richmenu',
                'done' => $oa && $liff && $oa->richMenus()->where('is_default', true)->get()->contains(fn ($m) => collect($m->actions)->contains(fn ($a) => ($a['type'] ?? '') === 'link' && str_contains($a['value'] ?? '', $liff->liff_id))),
                'actions' => [
                    ['open', 'Manager の「リッチメニュー」を開く', $oa ? route('mock.manager.oa.richmenus', $oa) : null],
                    ['click', '表示中のメニューの「編集」'],
                    ['select', 'A（ギフトを贈る）のタイプを「リンク」に'],
                    ['paste', 'A の URL にこれを貼る', $liff ? 'https://liff.line.me/'.$liff->liff_id : null],
                    ['check', 'B〜D（お店の情報・営業時間・Instagram）は触らない'],
                    ['click', '「保存」'],
                ],
                'why' => 'お店のメニューはそのままに、「贈る」ボタンの行き先だけを LIFF の URL（https://liff.line.me/{LIFF ID}）にします。ここまで終わると、お客さんのスマホのリッチメニューが押せるようになります。',
            ],
            [
                'key' => 'test', 'title' => '動作確認：お客さんのスマホから贈って、オーナーに届くか', 'who' => 'phone', 'chapter' => '10-purchase',
                'done' => Order::where(['store_id' => $s->id, 'route' => 'original'])->exists(),
                'actions' => [
                    ['open', '疑似スマホで、お客さんの「'.$s->name.'」のトークを開く', $oa ? route('mock.phone', ['chat' => $oa->id]) : route('mock.phone')],
                    ['click', 'リッチメニューの「ギフトを贈る」→ 商品の「この商品を送る」'],
                    ['paste', 'はじめてならカード番号にこれを入力（有効期限 12/40・CVC 123）', '4242424242424242'],
                    ['click', 'いちばん下の決済ボタン'],
                    ['check', '右のオーナーのスマホに「贈られました」が届いたら完成'],
                ],
                'why' => '運営スタッフがスマホを触るのは、この動作確認のときだけ。お客さん役・オーナー役の両方で確かめます。',
            ],
        ];

        // ★ 手順は飛ばせない：上から順に、前がすべて終わっているものだけ「完了」。最初の未完了が「いまここ」、それより後ろは「ロック」
        $open = true;
        foreach ($steps as $i => &$st) {
            $st['no'] = $i + 1;
            if ($open && $st['done']) { $st['state'] = 'done'; continue; }
            $st['state'] = $open ? 'current' : 'locked';
            $open = false;
        }
        unset($st);
        return $steps;
    }

    public function isDone(string $key): bool
    {
        return (collect($this->steps())->firstWhere('key', $key)['state'] ?? null) === 'done';
    }

    // 前に見たときより進んでいたら「完了！」を出すため、最後に見た完了数を覚えておく
    public function announce(array $steps): ?array
    {
        $done = collect($steps)->where('state', 'done')->count();
        $k = "build.seen.{$this->store->id}";
        $seen = (int) session($k, 0);
        session([$k => $done]);
        return $done > $seen ? $steps[$done - 1] : null;
    }
}
