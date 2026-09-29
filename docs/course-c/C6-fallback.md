# C6 外部サービスとつなぐ設計

## 読むファイル

- `app/Services/Notifier.php` … フォールバック
- `app/Services/Line/LineClient.php` … `base()`（疑似LINE か本物か）、`timeout(8)`、失敗しても止まらない
- `app/Services/MockLine/MockLine.php` の `sendWebhook()`

## ポイント

**1. フォールバック（代わりの道）**
店舗専用の Messaging API が未設定のお店でも、通知は **共通チャネル** から送ります。本番環境の管理画面の設定整合性にも「未設定 — 通知は共通チャネルから送信されます」と書いてあります。**設定が途中でもサービスが止まらない** 作りです。

**2. 外部サービスは「失敗するもの」として書く**
LINE社のAPIは、ネットワークが切れる・遅い・トークンが無効…で失敗します。`LineClient::call()` は、失敗しても例外で画面を落とさず、結果（ok / status）を返して記録します。`timeout(8)` で、相手が返事をしなくても8秒で諦めます。

**3. 接続先の切り替え**
`base()` は、チャネルIDかトークンが疑似LINEのものなら `/mock-line-api`、そうでなければ `https://api.line.me` に送ります。**同じコードで、疑似と本物の両方に対応** しています（コースBで本物につないでも、コードを変えなくてよかったのはこのため）。

**4. 本当は「キュー」を使う**
`WebhookController` は、Webhook を受けてその場で bot を動かしています。本物では、先に 200 を返して、返信などの処理は **キュー（後で順番に処理する仕組み）** に回すのが定石です（LINE社を待たせない・失敗したらやり直せる）。

## 課題

| レベル | 内容 |
|---|---|
| C6-1 | 疑似LINEの応答を遅くして（`ApiController::push()` に `sleep(10)`）、贈ったときに画面がどうなるか観察する。timeout を 3秒にしたら？ |
| C6-2 | お知らせの送信をキューに回す。`php artisan make:job SendLineMessage` → `Notifier` から `dispatch()`。`.env` の `QUEUE_CONNECTION=database` にして、`php artisan queue:work` で処理する |
| C6-3 | push が失敗したとき、3回まで間隔をあけてやり直す（ジョブの `$tries` と `backoff()`） |
