<?php

namespace App\Services;

use App\Models\Mock\Channel;
use App\Models\Mock\LiffApp;
use App\Models\Mock\OfficialAccount;
use App\Models\Mock\Provider;
use App\Models\Order;
use App\Models\Store;
use App\Services\MockLine\MockLine;

// =====================================================
// 構築ナビ：オリジナルのお店の公式LINEを、本番と同じ順番でつなぐための道案内
//
// 各ステップが「終わったか」は、画面の入力ではなく、実際のデータ（疑似LINE・自社DB）を見て判断します。
// だから、手順を飛ばしたり、値を間違えたりすると、そのステップは「まだ」のままになります。
// =====================================================
class BuildGuide
{
    public const ACCOUNTS = ['personal' => 'オーナー（個人アカウント）', 'company' => '会社の作業アカウント', 'admin' => '運営スタッフ（管理画面）', 'phone' => 'お客さんのスマホ'];

    public ?OfficialAccount $oa = null;
    public ?Channel $messaging = null;
    public ?Channel $login = null;
    public ?LiffApp $liff = null;

    public function __construct(public Store $store)
    {
        // このお店の公式アカウント：管理画面に入れたID → 構築中に作ったもの → 同じ名前のもの の順で探す
        $this->oa = OfficialAccount::byBasicId($store->line_official_account_id)
            ?? OfficialAccount::find(session("build.oa.{$store->id}"))
            ?? OfficialAccount::where('name', $store->name)->where('is_platform', false)->latest('id')->first();
        $this->messaging = $this->oa?->messagingChannel;
        // LIFF：エンドポイントURLがこのお店の slug のもの
        $this->liff = LiffApp::where('endpoint_url', 'like', '%/liff/s/'.$store->slug)->orderByDesc('liff_id')->first();
        $this->login = $this->liff?->channel ?? Channel::find(session("build.login.{$store->id}"));
    }

    public static function current(): ?self
    {
        $store = Store::find(session('build.store'));
        return $store ? new self($store) : null;
    }

    public function liffUrl(): string { return rtrim(PublicUrl::local(), '/').'/liff/s/'.$this->store->slug; }
    public function webhookUrl(): string { return rtrim(PublicUrl::local(), '/').'/api/webhook/line/store/'.$this->store->slug; }

    /** @return array<int, array> */
    public function steps(): array
    {
        $s = $this->store; $oa = $this->oa; $ch = $this->messaging; $login = $this->login; $liff = $this->liff;
        $platform = Provider::where('name', MockLine::PLATFORM_PROVIDER)->first();
        $slugReady = $s->is_approved && ! preg_match('/^(store|tmp)-/', $s->slug);

        $steps = [
            [
                'title' => '出店登録を確認して承認し、slug を決める',
                'who' => 'admin', 'chapter' => '01-store',
                'done' => $slugReady,
                'todo' => ['管理画面の店舗編集で、代表者・振込先が入っているか確認', '「スラッグ」を英小文字で決める（例 club-azure）', '「承認済み」をオンにして更新'],
                'why' => 'slug は LIFF と Webhook の URL に入る「お店の住所」。あとで変えると、LINE側の設定もすべて直すことになるので、最初に決めます。',
                'link' => route('admin.stores.edit', $s), 'linkLabel' => '管理画面の店舗編集を開く',
            ],
            [
                'title' => 'お店の公式アカウントに、会社のアカウントを招待してもらい、参加する',
                'who' => 'personal', 'who2' => 'company', 'chapter' => '03-official-account',
                'done' => $oa && $oa->roleOf('company'),
                // 招待URLを発行済みなら、次は会社のアカウントで参加する番
                'needs' => $oa && \Illuminate\Support\Facades\DB::connection('mockline')->table('invites')->where('official_account_id', $oa->id)->whereNull('used_by')->exists() ? 'company' : 'personal',
                'todo' => ["お店はもう公式アカウント（{$s->name}）を持っている。持ち主はオーナー", 'オーナー：設定 › 権限管理 → 手順書の権限（運用担当者）で「URLを発行」（本番では、オーナーにお願いして発行してもらう）', '「ログイン中」を会社の作業アカウントに切り替える', '会社：招待URLを開いて「参加する」'],
                'why' => 'この先の設定は運営が代わりに行います。オーナーのパスワードは聞かず、招待で「作業する人」に入れてもらいます。公式アカウントの持ち主（管理者）は、お店のままです。',
                'link' => $oa ? route('mock.manager.oa.members', $oa) : route('mock.manager'), 'linkLabel' => $oa ? '権限管理を開く' : 'Manager を開く',
            ],
            [
                'title' => 'Messaging API を有効にする（運営のプロバイダー）',
                'who' => 'company', 'chapter' => '03-official-account',
                'done' => $ch && $platform && $ch->provider_id == $platform->id,
                'bad' => $ch && $platform && $ch->provider_id != $platform->id ? 'Messaging API が運営以外のプロバイダーで有効になっています。プロバイダーはあとから変えられないので、公式アカウントから作り直しです。' : null,
                'todo' => ['Manager：設定 › Messaging API →「Messaging APIを利用する」', "プロバイダーは「".MockLine::PLATFORM_PROVIDER."」を選ぶ", 'この操作で Messaging API チャネルが自動で作られる'],
                'why' => 'プロバイダーは「会社の箱」。運営の箱に入れると、お客さんのユーザーIDが運営の他のチャネルとそろいます。あとから変えられません。',
                'link' => $oa ? route('mock.manager.oa.messaging', $oa) : null, 'linkLabel' => 'Messaging API 設定を開く',
                'doc' => 'https://developers.line.biz/ja/docs/messaging-api/getting-started/',
            ],
            [
                'title' => 'LINEログインチャネルを作って「公開」にする',
                'who' => 'company', 'chapter' => '04-liff',
                'done' => $login && $login->is_published,
                'todo' => ['LINE Developers：運営のプロバイダー →「新規チャネル作成」→ LINEログイン', "チャネル名は「{$s->name} LIFF」など、アプリタイプは「ウェブアプリ」", 'チャネル基本設定で「公開」にする'],
                'why' => 'LIFF は Messaging API チャネルには追加できず、LINEログインチャネルに追加します。「開発中」のままだと、お客さんは LIFF を開けません。',
                'link' => $login ? route('mock.developers.channel.show', $login) : ($platform ? route('mock.developers.provider', [$platform, 'create' => 'login']) : route('mock.developers')), 'linkLabel' => 'LINE Developers を開く',
                'doc' => 'https://developers.line.biz/ja/docs/liff/getting-started/',
            ],
            [
                'title' => 'LIFF アプリを追加する',
                'who' => 'company', 'chapter' => '04-liff',
                'done' => (bool) $liff,
                'todo' => ['LINEログインチャネルの「LIFF」タブ →「追加」', 'サイズ：Tall ／ Scope：openid・profile・chat_message.write', 'エンドポイントURL：'.$this->liffUrl(), '友だち追加オプション：On (normal)'],
                'why' => 'LIFF ID が「LINEの中で開くページ（贈る画面）」の入口になります。エンドポイントURL の slug が違うと、別のお店が開きます。',
                'link' => $login ? route('mock.developers.channel.show', [$login, 'tab' => 'liff']) : null, 'linkLabel' => 'LIFF タブを開く',
                'doc' => 'https://developers.line.biz/ja/docs/liff/registering-liff-apps/',
            ],
            [
                'title' => 'チャネルアクセストークン（長期）を発行する',
                'who' => 'company', 'chapter' => '05-five-values',
                'done' => $ch && $ch->access_token,
                'todo' => ['LINE Developers：Messaging API チャネル →「Messaging API設定」タブの一番下', '「チャネルアクセストークン（長期）」の「発行」'],
                'why' => '自社サーバーが LINE に「このお客さんに送って」とお願いするときの許可証です。人に見せない・チャットに貼らない。',
                'link' => $ch ? route('mock.developers.channel.show', [$ch, 'tab' => 'messaging']) : null, 'linkLabel' => 'Messaging API設定を開く',
                'doc' => 'https://developers.line.biz/ja/docs/basics/channel-access-token/',
            ],
            [
                'title' => '管理画面に5つの値を入れる',
                'who' => 'admin', 'chapter' => '05-five-values',
                'done' => $ch && $liff && $oa && $s->liff_id === $liff->liff_id && $s->line_messaging_channel_id === $ch->channel_id
                    && $s->line_messaging_channel_secret === $ch->secret && $s->line_messaging_channel_access_token === $ch->access_token
                    && mb_strtolower((string) $s->line_official_account_id) === mb_strtolower($oa->basic_id),
                'todo' => ['LIFF ID（LINEログインチャネル › LIFF）', 'チャネルID・チャネルシークレット（Messaging API チャネル › チャネル基本設定）', 'チャネルアクセストークン（Messaging API設定）', 'LINE公式アカウントID @xxx（Manager › アカウント設定）', '保存後に「設定整合性チェック」がすべてOKになるか見る'],
                'why' => 'この5つで、自社のお店と、LINE側の公式アカウント・チャネル・LIFF が結びつきます。1つでも別のお店の値が混ざると、つながりません。',
                'link' => route('admin.stores.edit', $s).'#line', 'linkLabel' => '管理画面の LINE設定を開く',
            ],
            [
                'title' => 'Webhook URL を登録して「検証」する',
                'who' => 'company', 'chapter' => '06-webhook',
                'done' => $ch && str_ends_with($ch->webhook_url, '/api/webhook/line/store/'.$s->slug) && $ch->webhook_verified_at,
                'todo' => ['Messaging API設定 › Webhook URL の「編集」', 'URL：'.$this->webhookUrl(), '「更新」→「検証」で「成功」が出る'],
                'why' => 'お客さんがトークで話しかけたとき、LINE社がこのURLに知らせてくれます。検証は、URL と署名（シークレット）が合っているかの確認です。',
                'link' => $ch ? route('mock.developers.channel.show', [$ch, 'tab' => 'messaging']) : null, 'linkLabel' => 'Messaging API設定を開く',
                'doc' => 'https://developers.line.biz/ja/docs/messaging-api/building-bot/',
            ],
            [
                'title' => '応答設定：Webhook をオン、応答メッセージをオフ',
                'who' => 'company', 'chapter' => '07-response',
                'done' => $ch && $ch->use_webhook && $oa && ! $oa->auto_reply_on && $oa->response_mode === 'bot',
                'todo' => ['Manager：設定 › 応答設定', 'チャット：オフ ／ Webhook：オン ／ 応答メッセージ：オフ', '（Developers の「Webhookの利用」と同じスイッチ）'],
                'why' => '応答メッセージがオンのままだと、LINE社の定型文と自社の案内の2通が返ります。',
                'link' => $oa ? route('mock.manager.oa.response', $oa) : null, 'linkLabel' => '応答設定を開く',
            ],
            [
                'title' => 'リッチメニューの「贈る」ボタンに、贈る画面の URL を入れる',
                'who' => 'company', 'chapter' => '08-richmenu',
                'done' => $oa && $liff && $oa->richMenus()->where('is_default', true)->get()->contains(fn ($m) => collect($m->actions)->contains(fn ($a) => ($a['type'] ?? '') === 'link' && str_contains($a['value'] ?? '', $liff->liff_id))),
                'todo' => ['Manager：トークルーム管理 › リッチメニュー → いま表示中のメニューの「編集」', '「ギフトを贈る」のボタン（A）のタイプを「リンク」に', "URL：https://liff.line.me/".($liff?->liff_id ?? '{LIFF ID}'), 'ほかのボタン（お店の情報など）はそのまま。「保存」'],
                'why' => 'お店がもともと使っているリッチメニューはそのままに、「贈る」ボタンの行き先だけを LIFF の URL にします。LIFF の URL は https://liff.line.me/{LIFF ID} の形です。',
                'link' => $oa ? route('mock.manager.oa.richmenus', $oa) : null, 'linkLabel' => 'リッチメニューを開く',
            ],
            [
                'title' => '動作確認：お客さん・オーナーのスマホで確かめる',
                'who' => 'phone', 'chapter' => '10-purchase',
                'done' => Order::where(['store_id' => $s->id, 'route' => 'original'])->exists(),
                'todo' => ['お客さんのスマホで、お店の公式LINEを開く（もう友だち）', '話しかけると、bot の案内が1通だけ返る', 'リッチメニューから贈る → オーナーのスマホに「贈られました」が届く'],
                'why' => '運営スタッフがスマホを触るのは、この動作確認のときだけ。お客さん役・オーナー役の両方で確かめます。',
                'link' => $oa ? route('mock.phone', ['chat' => $oa->id]) : route('mock.phone'), 'linkLabel' => '疑似スマホを開く',
            ],
        ];
        $current = null;
        foreach ($steps as $i => &$st) {
            $st['no'] = $i + 1;
            $st['state'] = $st['done'] ? 'done' : ($current === null ? 'current' : 'todo');
            if (! $st['done'] && $current === null) $current = $i;
        }
        return $steps;
    }

    public function currentIndex(array $steps): ?int
    {
        foreach ($steps as $i => $st) if ($st['state'] === 'current') return $i;
        return null;
    }
}
