<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Mock\Channel;
use App\Models\Mock\LiffApp;
use App\Models\Mock\LineUser;
use App\Models\Mock\Message;
use App\Models\Mock\OfficialAccount;
use App\Models\Mock\Provider;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Store;
use App\Services\MockLine\MockLine;
use Carbon\Carbon;
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

    // 変更前の状態（シナリオを作ったときに記録したもの）と、受講者が手順5で控えたスナップショット
    public function initial(): array { return $this->scenario ? BuildScenario::initial($this->scenario) : []; }
    public function snapshot(): array { return $this->scenario ? (json_decode(Setting::get("build.snapshot.{$this->scenario}", '{}'), true) ?: []) : []; }
    public function caseOf(): string { return $this->scenario ? BuildScenario::caseOf($this->scenario) : 'A'; }

    // スナップショットが実際の「変更前の状態」と合っているか
    public function snapshotOk(): bool
    {
        $i = $this->initial(); $sn = $this->snapshot();
        if (! $i || ! $sn) return false;
        return ($sn['auto_reply_on'] ?? null) === $i['auto_reply_on'] && (int) ($sn['keywords'] ?? -1) === (int) $i['keywords']
            && ($sn['greeting_on'] ?? null) === $i['greeting_on'] && ($sn['case'] ?? null) === $i['case'];
    }

    // リッチメニューの「贈る」以外のボタンが、スタート地点のまま変わっていないか
    public function otherButtonsIntact(): bool
    {
        if (! $this->scenario || ! $this->oa) return true;
        $menu = $this->oa->richMenus()->where('is_default', true)->latest('id')->first();
        if (! $menu) return false;
        $orig = BuildScenario::actions($this->scenario);
        foreach ($orig as $i => $a) {
            if ($a['type'] === 'none') continue;
            $now = $menu->actions[$i] ?? null;
            if (! $now || ($now['type'] ?? '') !== $a['type'] || trim((string) ($now['value'] ?? '')) !== $a['value']) return false;
        }
        return true;
    }

    // 最終確認：手順14のあと、お客さんが送ったメッセージへの返事が「ケースどおり」か
    //   B … キーワードを送ったら、お店の応答メッセージだけが返る（bot のギフト案内は返らない）
    //   A … 何か送ったら、bot のギフト案内だけが返る（LINE社の応答メッセージは返らない）
    public function finalCheck(): array
    {
        if (! $this->oa) return [false, null];
        $guest = LineUser::me('customer');
        $msgs = Message::where(['official_account_id' => $this->oa->id, 'user_id' => $guest->user_id])->orderBy('id')->get();
        $first = Order::where(['store_id' => $this->store->id, 'route' => 'original'])->min('created_at');
        $since = $first ? Carbon::parse($first) : null;
        $keywords = $this->initial()['keyword_list'] ?? [];
        $result = [false, null];
        foreach ($msgs as $i => $m) {
            if ($m->direction !== 'in' || $m->via !== 'user' || ! $since || $m->created_at->lt($since)) continue;
            if ($this->caseOf() === 'B' && ! in_array(trim($m->text), $keywords, true)) continue;
            $replies = collect();
            foreach ($msgs->slice($i + 1) as $n) { if ($n->direction === 'in') break; $replies->push($n->via); }
            $ok = $this->caseOf() === 'B' ? $replies->contains('auto') && ! $replies->contains('bot') : $replies->contains('bot') && ! $replies->contains('auto');
            $result = [$ok, "「{$m->text}」→ ".($replies->isEmpty() ? '返事なし' : $replies->map(fn ($v) => ['auto' => '応答メッセージ', 'bot' => 'bot'][$v] ?? $v)->join('・'))];
        }
        return $result;
    }

    private static function similar(string $a, string $b): bool
    {
        $n = fn ($x) => strtr($x, ['I' => 'l', '1' => 'l', 'O' => '0', 'o' => '0']);
        return $a !== $b && $n($a) === $n($b);
    }

    /** @return array<int, array> */
    public function steps(): array
    {
        $s = $this->store; $oa = $this->oa; $ch = $this->messaging; $login = $this->login; $liff = $this->liff;
        $platform = Provider::where('name', MockLine::PLATFORM_PROVIDER)->first();
        $slug = $this->targetSlug();
        $platformOa = OfficialAccount::where('is_platform', true)->first();
        $ga = $this->scenario ? BuildScenario::giftArea($this->scenario) : ['letter' => 'A', 'label' => 'ギフトを贈る', 'others' => []];
        $inviteOpen = $oa && DB::connection('mockline')->table('invites')->where('official_account_id', $oa->id)->whereNull('used_by')->exists();
        $bc = config('lab.build');
        $case = $this->caseOf();
        $agent = $this->scenario ? BuildScenario::expectedAgent($this->scenario) : null;
        $snap = $this->snapshot();
        $keywordItems = $oa ? $oa->autoReplies()->get()->reject->isCatchAll() : collect();
        $namePrefix = $bc['service_name'].' ';

        $steps = [
            [
                'key' => 'approve', 'title' => '出店登録を確認して承認し、slug を決める', 'who' => 'admin', 'chapter' => '01-store',
                'done' => $s->is_approved && $s->slug === $slug,
                'actions' => [
                    ['open', '管理画面の店舗編集を開く', route('admin.stores.edit', $s)],
                    ['check', '「代表者情報」「振込先情報」に値が入っているか見る'],
                    ['paste', '「スラッグ」欄の store-○ を消して、これを入力（依頼書で決まっている slug）', $slug],
                    ['select', '「管理設定」の「承認済み」にチェック'],
                    ['click', 'ページ一番下の「更新」'],
                ],
                'why' => 'slug は LIFF と Webhook の URL に入る「お店の住所」。あとで変えると LINE 側の設定もすべて直すことになるので、最初に決めます。',
            ],
            [
                'key' => 'menu', 'title' => '商品が1件以上「販売中」になっているか確かめる', 'who' => 'admin', 'chapter' => '02-menu',
                'done' => $s->menus()->where('is_active', true)->exists(),
                'actions' => [
                    ['open', '管理画面の店舗詳細を開く', route('admin.stores.show', $s)],
                    ['check', '「メニュー」の件数だけでなく、状態が「販売中」かを見る（全部「停止中」だとお客さんは何も買えない）'],
                    ['check', '全部「停止中」なら、本番ではお店に「商品を販売中にしてください」と案内する。体験では、どれか1つの「編集」→「販売中」にチェック →「更新」'],
                ],
                'why' => '本番で、商品が0件や全部「無効」のまま構築を終え、お客さんが何も買えなかったことがあります。件数だけでなく状態まで確かめます。',
            ],
            [
                'key' => 'agent', 'title' => '紐付け代理店を確かめる', 'who' => 'admin', 'chapter' => '09-agent',
                'done' => $agent ? (int) $s->agent_id === $agent->id : ! $s->agent_id,
                'bad' => $agent && $s->agent_id && (int) $s->agent_id !== $agent->id ? '紐付けた代理店が依頼とちがいます。紹介コードまで見比べてください。' : null,
                'actions' => array_merge([
                    ['open', '管理画面の店舗編集を開く', route('admin.stores.edit', $s)],
                    ['check', $agent ? "依頼書の紹介元：「{$agent->name}」（紹介コード {$agent->referral_code}）" : '依頼書の紹介元：なし（代理店の紹介ではない）'],
                    ['check', '「管理設定」の「紐付け代理店」を見る。出店登録のときに紐付け済みのこともある'],
                ], $agent ? [['select', 'ちがっていたら、紹介コードまで同じものを選んで「更新」']] : [['select', '何か選ばれていたら「紐付けなし」にして「更新」']]),
                'why' => '代理店への報酬は、この紐付けで計算されます。2次代理店は、1次代理店の下にインデントされて出ます。',
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
                'key' => 'snapshot', 'title' => '何も変える前に、いまの設定を控えて「ケースA／B」を判定する', 'who' => 'company', 'chapter' => '07-response',
                'done' => $this->snapshotOk(),
                'bad' => $snap && ! $this->snapshotOk() ? '控えた内容が、実際の「変更前の状態」と合っていません。Manager の画面をもう一度見て、控え直してください。' : null,
                'form' => 'snapshot',
                'actions' => [
                    ['switch', '「ログイン中」を会社の作業アカウントに', 'company'],
                    ['open', 'Manager の「応答設定」を開く', $oa ? route('mock.manager.oa.response', $oa) : null],
                    ['check', '「応答メッセージ」「あいさつメッセージ」のオン・オフを見る（まだ何も切り替えない）'],
                    ['open', 'Manager の「応答メッセージ」一覧を開く', $oa ? route('mock.manager.oa.auto-replies', $oa) : null],
                    ['check', 'キーワードの付いた応答が何件あるかを数える（「一律応答」は数えない）'],
                    ['open', 'Manager の「リッチメニュー」を開き、ボタンごとの動き（リンク・テキスト）を見ておく', $oa ? route('mock.manager.oa.richmenus', $oa) : null],
                    ['paste', '下の欄に控えて、ケースを判定して「控える」'],
                ],
                'why' => '本番で、お店が使っていたキーワード応答を「標準手順だから」とオフにしてしまい、お店のメニューボタンがすべて自社サービスの案内になる事故がありました。変える前に控えておけば、判定もでき、元にも戻せます。キーワード応答が1件でもあれば「ケースB（運用中）」です。',
            ],
            [
                'key' => 'messaging', 'title' => 'Messaging API を有効にする（運営のプロバイダー）', 'who' => 'company', 'chapter' => '03-official-account',
                'done' => $ch && $platform && $ch->provider_id == $platform->id && $ch->privacy_url === $bc['privacy_url'],
                'bad' => $ch && $platform && $ch->provider_id != $platform->id ? '運営以外のプロバイダーで有効になっています。プロバイダーはあとから変えられないので、構築ナビでお店を選び直して最初からやり直してください。'
                    : ($ch && $ch->privacy_url !== $bc['privacy_url'] ? 'プライバシーポリシーが会社のURLになっていません。Manager の「Messaging API」の下の欄で直せます。' : null),
                'actions' => [
                    ['switch', '「ログイン中」を会社の作業アカウントに', 'company'],
                    ['open', 'Manager の「設定 › Messaging API」を開く', $oa ? route('mock.manager.oa.messaging', $oa) : null],
                    ['check', '① 開発者情報はそのままでよい'],
                    ['select', '② プロバイダーで「'.MockLine::PLATFORM_PROVIDER.'」を選ぶ'],
                    ['paste', '③ プライバシーポリシーに会社のURLを入れる（利用規約は空でよい）', $bc['privacy_url']],
                    ['click', '「Messaging APIを利用する」→ 確認で「OK」'],
                    ['check', 'Channel ID と Channel secret が表示されたら完了'],
                ],
                'why' => 'プロバイダーは「会社の箱」。運営の箱に入れると、お客さんのユーザーIDが運営のサービス全体でそろいます。あとから変えられません。',
            ],
            [
                'key' => 'login', 'title' => 'LINEログインチャネルを作って「公開」にする', 'who' => 'company', 'chapter' => '04-liff',
                'done' => $login && $login->is_published && ! $login->two_factor && $login->email === $bc['email'] && $login->privacy_url === $bc['privacy_url'] && $login->country === 'JP',
                'bad' => $login ? collect([
                    $login->two_factor ? '2要素認証の必須化がオンです（オフにする）' : null,
                    $login->email !== $bc['email'] ? 'メールアドレスが会社の共通アドレスではありません' : null,
                    $login->privacy_url !== $bc['privacy_url'] ? 'プライバシーポリシーURLが入っていません（本番では必須）' : null,
                    $login->country !== 'JP' ? '所在国・地域が「日本」ではありません' : null,
                ])->filter()->map(fn ($x) => '・'.$x)->join("\n") ?: null : null,
                'actions' => $login ? [
                    ['check', 'チャネルはできています。基本設定を確かめて公開します'],
                    ['open', 'LINEログインチャネルの「チャネル基本設定」を開く', route('mock.developers.channel.show', $login)],
                    ['check', 'メール・プライバシーポリシー・所在国・2要素認証（オフ）が合っているか。ちがえば「編集」で直す'],
                    ['click', '「チャネルの状態」の「公開」'],
                    ['check', '「公開済み」になったら完了'],
                ] : [
                    ['open', 'LINE Developers の新規チャネル作成を開く', $platform ? route('mock.developers.provider', [$platform, 'create' => 'login']) : route('mock.developers')],
                    ['select', '所在国・地域「日本」'],
                    ['paste', '「チャネル名」は「'.$namePrefix.'{店名} LIFF」。'.$bc['channel_name_max'].'文字を超えるときは店名を短くする'],
                    ['paste', '「チャネル説明」は何でもよい（例：{店名} ギフト用LIFF）'],
                    ['select', 'アプリタイプ「ウェブアプリ」。「2要素認証の必須化」はオフ'],
                    ['paste', 'メールアドレスは会社の共通アドレス（個人のアドレスが最初から入っているので消す）', $bc['email']],
                    ['paste', 'プライバシーポリシーURL', $bc['privacy_url']],
                    ['click', '「開発者契約に同意」にチェック →「作成」'],
                ],
                'why' => 'LIFF は Messaging API チャネルには追加できず、LINEログインチャネルに追加します。「開発中」のままだと、お客さんが LIFF を開くと「bad request」になります（本番で実際に起きました）。2要素認証がオンだと、お客さんのログインが面倒になります。',
            ],
            [
                'key' => 'liff', 'title' => 'LIFF アプリを追加する（贈る画面の入口）', 'who' => 'company', 'chapter' => '04-liff',
                'done' => $liff && $liff->size === 'Full' && $liff->bot_prompt && collect(explode(' ', $liff->scopes))->sort()->values()->all() === ['openid', 'profile'] && $liff->name === $slug.$bc['liff_suffix'],
                'bad' => $liff ? collect([
                    $liff->name !== $slug.$bc['liff_suffix'] ? "LIFFアプリ名は「{$slug}{$bc['liff_suffix']}」にします" : null,
                    $liff->size !== 'Full' ? 'サイズは Full にします' : null,
                    collect(explode(' ', $liff->scopes))->sort()->values()->all() !== ['openid', 'profile'] ? 'Scope は openid と profile の2つだけにします' : null,
                    ! $liff->bot_prompt ? '友だち追加オプションは On (normal) にします' : null,
                ])->filter()->map(fn ($x) => '・'.$x)->join("\n").'（LIFFアプリ名を押すと「LIFFアプリ詳細」で直せます）' : null,
                'actions' => [
                    ['open', 'LINEログインチャネルの「LIFF」タブの追加画面を開く', $login ? route('mock.developers.channel.show', [$login, 'tab' => 'liff', 'add' => 1]) : null],
                    ['paste', 'LIFFアプリ名', $slug.$bc['liff_suffix']],
                    ['select', 'サイズは「Full」'],
                    ['paste', '「エンドポイントURL」← 管理画面の店舗編集にある「LIFF のエンドポイントURL」をコピー', null, route('admin.stores.edit', $s).'#line'],
                    ['select', 'Scope は openid と profile（chat_message.write は付けない）'],
                    ['select', '友だち追加オプションは「On (normal)」'],
                    ['click', '「追加」'],
                    ['check', '表に LIFF ID と LIFF URL が出たら完了（あとの手順で使う）'],
                ],
                'why' => 'LIFF ID が「LINEの中で開くページ（贈る画面）」の入口番号です。エンドポイントURL の slug が違うと、別のお店が開きます。',
            ],
            [
                'key' => 'link', 'title' => 'ログインチャネルに、お店の公式アカウントをリンクする', 'who' => 'company', 'chapter' => '04-liff',
                'done' => $login && $oa && (int) $login->linked_oa_id === $oa->id,
                'bad' => $login && $oa && $login->linked_oa_id && (int) $login->linked_oa_id !== $oa->id ? '別の公式アカウントがリンクされています。' : null,
                'actions' => [
                    ['open', 'LINEログインチャネルの「チャネル基本設定」を開く', $login ? route('mock.developers.channel.show', $login) : null],
                    ['select', '下の「友だち追加オプション」の「リンクされたLINE公式アカウント」で、このお店の @ID を選ぶ（@ID は Manager の「アカウント設定」で確かめる）', null, $oa ? route('mock.manager.oa.settings', $oa) : null],
                    ['click', '「更新」'],
                ],
                'why' => 'LIFF の友だち追加オプションは、ここでリンクした公式アカウントの友だち追加をすすめます。リンクしないと、友だち追加オプションが効きません。',
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
                'bad' => $liff && $s->liff_id && self::similar($s->liff_id, $liff->liff_id) ? 'LIFF ID が1文字ちがいます。I（大文字アイ）と l（小文字エル）、O と 0 を見まちがえていませんか？ 目で写さず、コピーで貼りましょう。'
                    : ($login && $s->line_messaging_channel_id === $login->channel_id ? '「Messaging API チャネルID」に LINEログインチャネルのIDが入っています。' : null),
                'actions' => [
                    ['open', '管理画面の店舗編集「LINE 連携設定」を開く（コピー元とは別のタブで開くと楽）', route('admin.stores.edit', $s).'#line'],
                    ['paste', '「LIFF ID」← LINEログインチャネルの LIFF タブ →「LIFFアプリ詳細」の「コピー」', null, $login ? route('mock.developers.channel.show', [$login, 'tab' => 'liff']) : null],
                    ['paste', '「Messaging API チャネルID」← Messaging API チャネルの「チャネル基本設定」（LINEログインチャネルのIDではない）', null, $ch ? route('mock.developers.channel.show', $ch) : null],
                    ['paste', '「チャネルシークレット」← 同じ「チャネル基本設定」', null, $ch ? route('mock.developers.channel.show', $ch) : null],
                    ['paste', '「アクセストークン（長期）」←「Messaging API設定」のいちばん下', null, $ch ? route('mock.developers.channel.show', [$ch, 'tab' => 'messaging']) : null],
                    ['paste', '「LINE公式アカウントID」← Manager の「アカウント設定」のベーシックID', null, $oa ? route('mock.manager.oa.settings', $oa) : null],
                    ['check', 'どれも目で写さず、コピー → 貼り付け。貼ったら最初と最後の数文字を見比べる'],
                    ['click', 'いちばん下の「更新」→ 下の「設定整合性」がすべて OK か見る'],
                ],
                'why' => 'この5つで、自社のお店と LINE 側の公式アカウント・チャネル・LIFF が結びつきます。本番では LIFF ID の I と l を見まちがえて「システムエラー」になったことがあります。設定整合性は「形」しか見ないので、まちがった LIFF ID でも OK になります。',
            ],
            [
                'key' => 'webhook', 'title' => 'Webhook URL を登録して「検証」する', 'who' => 'company', 'chapter' => '06-webhook',
                'done' => $ch && str_ends_with($ch->webhook_url, '/api/webhook/line/store/'.$slug) && $ch->webhook_verified_at,
                'actions' => [
                    ['open', 'Messaging API チャネルの「Messaging API設定」タブを開く', $ch ? route('mock.developers.channel.show', [$ch, 'tab' => 'messaging']) : null],
                    ['click', '「Webhook URL」の「編集」'],
                    ['paste', '管理画面の「店舗専用 Webhook URL」の「コピー」で取った値を貼る', null, route('admin.stores.edit', $s).'#line'],
                    ['click', '「更新」→「検証」'],
                    ['check', '「成功（200）」が出たら完了'],
                ],
                'why' => 'お客さんがトークで話しかけたとき、LINE社がこのURLに知らせてくれます。検証は、URL と署名（シークレット）が合っているかの確認です。5つの値を入れる前に検証すると失敗します。',
            ],
            $case === 'B' ? [
                'key' => 'response', 'title' => '応答設定（ケースB：お店の応答は残し、自社の自動返信をオフ）', 'who' => 'company', 'chapter' => '07-response',
                'done' => $ch && $ch->use_webhook && $oa && $oa->response_mode === 'bot' && $oa->auto_reply_on && $oa->greeting_on
                    && $keywordItems->every('enabled') && $keywordItems->count() >= (int) ($this->initial()['keywords'] ?? 0) && ! $s->auto_reply_enabled,
                'bad' => $oa && (! $oa->auto_reply_on || $keywordItems->contains(fn ($a) => ! $a->enabled)) ? 'お店のキーワード応答が止まっています（本番で実際に起きた事故と同じ状態）。応答メッセージをオンに戻し、一覧の「利用」がすべてオンか確かめてください。'
                    : ($oa && ! $oa->greeting_on ? 'あいさつメッセージがオフです。運用中のお店なので、オンのまま残します。' : null),
                'actions' => [
                    ['open', 'Manager の「設定 › 応答設定」を開く', $oa ? route('mock.manager.oa.response', $oa) : null],
                    ['check', 'スイッチは切り替えた瞬間に保存される（保存ボタンはない）ので、押す前に確かめる'],
                    ['select', '「チャット」はオフ（オンなら切り替える）'],
                    ['select', '「Webhook」をオン'],
                    ['check', '「応答メッセージ」「あいさつメッセージ」は、控えたとおりオンのまま。さわらない'],
                    ['open', '管理画面の店舗編集で「テキスト受信時にギフト誘導を自動返信する」をオフ →「更新」', route('admin.stores.edit', $s).'#line'],
                ],
                'why' => 'お店はキーワード応答で「お店の情報」「営業時間」などを返しています。応答メッセージをオフにすると、それが全部止まります。Webhook（購入・通知に必要）はオンにし、自社のギフト案内の自動返信をオフにすれば、返事が2通にならず、お店の応答もそのまま動きます。',
            ] : [
                'key' => 'response', 'title' => '応答設定（ケースA：Webhook をオン、応答・あいさつをオフ）', 'who' => 'company', 'chapter' => '07-response',
                'done' => $ch && $ch->use_webhook && $oa && ! $oa->auto_reply_on && ! $oa->greeting_on && $oa->response_mode === 'bot' && $s->auto_reply_enabled,
                'bad' => $oa && $keywordItems->isNotEmpty() ? 'この公式アカウントにはキーワード応答があります。本当にケースAですか？' : null,
                'actions' => [
                    ['open', 'Manager の「設定 › 応答設定」を開く', $oa ? route('mock.manager.oa.response', $oa) : null],
                    ['check', 'スイッチは切り替えた瞬間に保存される（保存ボタンはない）'],
                    ['select', '「チャット」はオフ'],
                    ['select', '「あいさつメッセージ」をオフ'],
                    ['select', '「Webhook」をオン'],
                    ['select', '「応答メッセージ」をオフ'],
                    ['check', '管理画面の「テキスト受信時にギフト誘導を自動返信する」はオンのまま'],
                ],
                'why' => 'お店が応答を使っていない公式アカウントなので、LINE社の定型文を止め、自社の bot（ギフト案内）に返事をまかせます。あいさつは、自社が友だち追加（follow）で送る案内と2通にならないようオフにします。',
            ],
            [
                'key' => 'richmenu', 'title' => 'リッチメニューの「'.$ga['label'].'」に、LIFF の URL を入れる', 'who' => 'company', 'chapter' => '08-richmenu',
                'done' => $oa && $liff && $this->otherButtonsIntact() && $oa->richMenus()->where('is_default', true)->get()->contains(fn ($m) => collect($m->actions)->contains(fn ($a) => ($a['type'] ?? '') === 'link' && preg_match('#^https://liff\.line\.me/'.preg_quote($liff->liff_id, '#').'(\?|$)#', $a['value'] ?? ''))),
                'bad' => $oa && ! $this->otherButtonsIntact() ? '「'.$ga['label'].'」以外のボタンが、スタート地点から変わっています。お店のボタンは元に戻してください（手順5で見ておいた内容）。' : null,
                'actions' => [
                    ['check', '本番では、リッチメニューを「誰が」設定するか（運営／お店）を先に確認する。この課題は運営が設定する'],
                    ['open', 'Manager の「リッチメニュー」を開く', $oa ? route('mock.manager.oa.richmenus', $oa) : null],
                    ['click', '表示中のメニューの「編集」'],
                    ['select', $ga['letter'].'（'.$ga['label'].'）のタイプを「リンク」に'],
                    ['paste', $ga['letter'].' の URL ← LIFF タブの「LIFF URL」（https://liff.line.me/{LIFF ID}）をコピー', null, $login ? route('mock.developers.channel.show', [$login, 'tab' => 'liff']) : null],
                    ['check', ($ga['others'] ? implode('・', $ga['others']) : 'ほかのボタン').' は触らない'],
                    ['click', '「保存」'],
                ],
                'why' => 'お店のメニューはそのままに、「'.$ga['label'].'」ボタンの行き先だけを LIFF の URL にします。送信履歴（領収書）やお礼一覧のボタンを作るときは、同じ URL の後ろに ?action=send-history ／ ?action=thanks を付けます。ここまで終わると、お客さんのスマホのリッチメニューが押せるようになります。',
            ],
            [
                'key' => 'test', 'title' => '動作確認：お客さんのスマホから贈って、オーナーに届くか', 'who' => 'phone', 'chapter' => '10-purchase',
                'done' => Order::where(['store_id' => $s->id, 'route' => 'original'])->exists(),
                'actions' => [
                    ['open', '疑似スマホで、お客さんの「'.$s->name.'」のトークを開く', $oa ? route('mock.phone', ['chat' => $oa->id]) : route('mock.phone')],
                    ['click', 'リッチメニューの「'.$ga['label'].'」→ 商品の「この商品を送る」'],
                    ['paste', 'はじめてならカード番号にこれを入力（有効期限 12/40・CVC 123）', '4242424242424242'],
                    ['click', 'いちばん下の決済ボタン'],
                    ['check', '「DELIVERED」の画面が出たら、お客さん側はOK（次の手順でオーナー側を確かめる）'],
                ],
                'why' => '運営スタッフがスマホを触るのは、この動作確認のときだけ。まずお客さん役で「贈れるか」を確かめます（本番では実際にお金が動くので、テスト購入するかはお店・上長と相談）。',
            ],
            [
                'key' => 'owner', 'title' => 'オーナーのスマホで、届いたギフトを確認して「受け取る」', 'who' => 'phone', 'chapter' => '10-purchase',
                'done' => Order::where(['store_id' => $s->id, 'route' => 'original'])->whereIn('status', [OrderStatus::Received, OrderStatus::Thanked])->exists(),
                'actions' => [
                    ['open', '疑似スマホで、右のオーナーの「おくりギフト(dev)」のトークを開く', $platformOa ? route('mock.phone', ['to' => 'owner', 'chat' => $platformOa->id]) : route('mock.phone')],
                    ['check', 'トークに「['.$s->name.']」の「贈られました」のお知らせが届いている'],
                    ['click', 'リッチメニューの「店舗管理」（下の段のまん中）'],
                    ['select', 'お店の一覧から「'.$s->name.'」のカードを探す（オーナーのお店が全部ならんでいます）'],
                    ['click', 'そのカードの黄色い「贈り物一覧」（受取待ちの数が出ています）'],
                    ['click', 'いま贈ったギフトを開いて「受け取る」'],
                    ['check', '左のお客さんのスマホに「受け取りました🍾」が届く'],
                ],
                'why' => 'オーナーは、運営の公式LINE（おくりギフト）の「店舗管理」で自分のお店を選び、「贈り物一覧」で届いたギフトを確かめます。本番でテスト購入したときも、ここを見れば届いたかがすぐにわかります。',
            ],
            [
                'key' => 'final', 'title' => '引き渡し前の最終確認：お店のいつもの動きが変わっていないか', 'who' => 'phone', 'chapter' => '10-purchase',
                'done' => $this->otherButtonsIntact() && $this->finalCheck()[0],
                'bad' => ($fc = $this->finalCheck())[1] && ! $fc[0] ? "送ってみた結果：{$fc[1]}。".($case === 'B' ? 'お店の応答メッセージだけが返るはずです。' : 'bot のギフト案内だけが返るはずです。') : null,
                'actions' => $case === 'B' ? [
                    ['open', '疑似スマホで、お客さんの「'.$s->name.'」のトークを開く', $oa ? route('mock.phone', ['chat' => $oa->id]) : route('mock.phone')],
                    ['click', 'リッチメニューの、テキストを送るボタン（'.implode('・', $ga['others']).' のどれか）を押す'],
                    ['check', 'お店のキーワード応答（下に小さく「応答メッセージ」）だけが返り、ギフト案内（bot）は返らない'],
                    ['check', '「'.$ga['label'].'」以外のボタンの動きが、手順5で見たときと同じ'],
                ] : [
                    ['open', '疑似スマホで、お客さんの「'.$s->name.'」のトークを開く', $oa ? route('mock.phone', ['chat' => $oa->id]) : route('mock.phone')],
                    ['paste', '左下のキーボードのアイコンを押して、何か送る（例：今日空いてる？）'],
                    ['check', 'ギフトの案内（下に小さく「bot」）だけが返り、LINE社の定型文（応答メッセージ）は返らない'],
                    ['check', '「'.$ga['label'].'」以外のボタンの動きが、手順5で見たときと同じ'],
                ],
                'why' => '「動いた」で終わらせず、お店がもともと使っていた動きが壊れていないかを、お店に渡す前に自分で確かめます。本番の事故は、ここを見ていれば防げました。',
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
