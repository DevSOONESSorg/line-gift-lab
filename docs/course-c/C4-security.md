# C4 セキュリティ

## 読むファイル

- `bootstrap/app.php` … CSRF の例外、ログインが必要なページ
- `app/Http/Controllers/WebhookController.php`、`app/Services/Line/Signature.php` … 署名
- `app/Models/Store.php` の `$casts` … `'encrypted'`
- `app/Http/Controllers/Admin/LoginController.php` … ログイン
- `app/Http/Middleware/IdentifyLineUser.php` … LIFF の「誰が開いたか」

## ポイント

| 守りたいもの | 仕組み | どこ |
|---|---|---|
| 画面のフォームを、よそのサイトから勝手に送信されない | **CSRF トークン**（フォームの `@csrf`＝実物の `_token`） | 全フォーム |
| Webhook が本物のLINE社から来たか | **署名**（HMAC-SHA256。シークレットはネットを流れない） | `WebhookController` |
| DBを見られても、シークレット・トークンを使われない | **暗号化して保存**（APP_KEY で暗号化） | `Store` の `encrypted` |
| 管理画面に関係ない人が入れない | **ログイン**（セッション＋Cookie）。ログイン時にセッションIDを作り直す | `LoginController` |
| 取り返しのつかない操作を誤って押さない | **確認ダイアログ**（`data-confirm`） | 取消・削除ボタン |

Webhook のURL（`/api/...`）は CSRF チェックの対象外です（サーバー同士の通信なので）。**その代わりに署名で守っている**、という関係を理解しましょう。

## 教材の「わざと甘いところ」

`IdentifyLineUser` は、LIFF の画面から送られてきた `userId` をそのまま信じています。悪い人がブラウザの開発者ツールで別の人の `userId` を送れば、なりすませてしまいます。
本物は、LIFF の **IDトークン**（`liff.getIDToken()`）をサーバーに送り、LINE社のAPI（`POST https://api.line.me/oauth2/v2.1/verify`）で検証してから userId を信じます。

## 確かめる

```bash
docker compose exec app php artisan tinker
```

```php
DB::table('stores')->where('slug', 'sample-cafe')->value('line_messaging_channel_secret');   // 暗号化された文字列
App\Models\Store::where('slug', 'sample-cafe')->first()->line_messaging_channel_secret;      // モデル経由だと復号される
```

## 課題

| レベル | 内容 |
|---|---|
| C4-1 | 管理画面のログインを、5回失敗したら1分間ロックする（ヒント：Laravel の `RateLimiter`） |
| C4-2 | 店舗の「削除」ボタンにある確認ダイアログを外すとどうなるか、なぜ確認が必要かを、本番の管理画面を見て気づいたことと合わせて説明する（文章でOK） |
| C4-3 | IDトークンの検証を実装する（コースBの本物のLINEで）。`/liff/session` に `idToken` を送り、サーバーで verify してから userId をセッションに入れる。検証に失敗したら 401 |
