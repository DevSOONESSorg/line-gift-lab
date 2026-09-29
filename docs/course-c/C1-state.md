# C1 注文の状態遷移

## 読むファイル

- `app/Enums/OrderStatus.php` … 状態の一覧と `next()`（移ってよい先）
- `app/Services/OrderService.php` … `create()` と `transition()`
- `database/migrations/…create_gift_tables.php` の `order_status_logs`

## ポイント

**1. 状態は「文字列」ではなく enum で扱う**
`$order->status` は `'requested'` という文字列ではなく `OrderStatus::Requested` という値です（`app/Models/Order.php` の `$casts`）。打ち間違い（`'recieved'` など）がそもそも書けません。

**2. 状態を変えるのは `OrderService::transition()` だけ**
画面ごとに `$order->update(['status' => ...])` と書くと、いつか「期限切れなのに受取済み」のような、ありえない状態が生まれます。1か所に集めて、`canTransitionTo()` で必ずチェックしています。

**3. 状態を変えるときは「履歴」も一緒に残す（トランザクション）**
`DB::transaction()` の中で、注文の更新と `order_status_logs` への記録を **両方** 行います。途中で失敗したら両方なかったことになります（片方だけ書かれる、がない）。注文詳細の「状態の移り変わり」の表はこの記録です。

## 確かめる

1. オーナー役でギフトを「受け取る」→ もう一度同じ注文の「受け取る」を押す（ブラウザの戻るで画面を戻して押す）→「受取済みの注文を受取済みにはできません」
2. tinker で、わざとありえない移り変わりを試す

```php
$o = App\Models\Order::where('status', 'expired')->first();
App\Services\OrderService::receive($o);   // → RuntimeException
```

## 課題

| レベル | 内容 |
|---|---|
| C1-1 | 状態の日本語表示「期限切れ (カード)」を「期限切れ（受け取りなし）」に変える。どのファイルを1か所変えれば、全画面に反映される？ |
| C1-2 | 「リクエスト中」の注文を、オーナーが **辞退**（お断り）できるようにする。状態 `declined`（辞退）を enum に足し、`Requested → Declined` を許可し、ギフト一覧に「辞退する」ボタンを付ける。辞退したら仮押さえは解放（お金は動かない） |
| C1-3 | 状態遷移のテストを書く（`tests/Unit/OrderStatusTest.php`）。「期限切れ → 受取済み は false」「入金待ち → リクエスト中 は true」などを確かめ、`docker compose exec app php artisan test` で実行 |
