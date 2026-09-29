# C2 決済と期限

## 読むファイル

- `config/lab.php` … `card_authorization_days`（与信の日数）、`bank_transfer_days`（入金期限）
- `app/Services/OrderService.php` … `create()` の `expires_at`、`receive()`、`expireOverdue()`
- `routes/console.php` … スケジューラー
- `app/Console/Commands/ExpireOrders.php`、`TimeTravel.php`
- `docker/start.sh` … `php artisan schedule:work` を裏で動かしている行

## ポイント

**与信（仮押さえ）と確定（売上計上）は別のタイミング**

| タイミング | カード | 銀行振込 |
|---|---|---|
| 贈ったとき | 与信（枠を押さえるだけ）→ リクエスト中 | 入金待ち |
| 期限 | 与信の期限（教材では7日）を過ぎたら期限切れ | 入金期限（3日）を過ぎたらキャンセル |
| オーナーが受け取ったとき | 確定（お金が動く） | 入金済みのお金を売上に |

本物の決済サービスでも、与信には有効期限があり、期限を過ぎると確定できなくなります。だから「期限切れ」を自動で処理する必要があります。

**「決まった時間に動く処理」はスケジューラーで**
`routes/console.php` の `Schedule::command('orders:expire')->everyMinute();` が「毎分、期限切れを探して処理する」という予定です。Docker の中で `schedule:work` がそれを動かし続けています。

## 確かめる

```bash
docker compose exec app php artisan schedule:list
docker compose exec app php artisan lab:time-travel {注文ID}
docker compose exec app php artisan orders:expire
```

## 課題

| レベル | 内容 |
|---|---|
| C2-1 | 与信の日数を 7日 → 5日 に変える。1か所だけ変えればよい場所はどこ？ 変えたあと、新しい注文の期限がどうなったか確認 |
| C2-2 | 期限切れになったとき、お客さんに「お店が受け取らなかったため、ギフトは取り消されました（お金は動いていません）」とLINEで知らせる。ヒント：`expireOverdue()` の中で `Notifier::toCustomer()` |
| C2-3 | 期限の **前日** に、オーナーへ「明日で期限切れになるギフトがあります」と知らせるコマンドを作り、毎日10時に動くようスケジューラーに登録する（`->dailyAt('10:00')`）。同じ注文に2回知らせない工夫も考える |
