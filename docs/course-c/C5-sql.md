# C5 集計と SQL

## 読むファイル

- `app/Http/Controllers/Admin/SalesController.php`
- `app/Http/Controllers/Admin/AgentController.php` の `show()`、`app/Models/Agent.php` の `rewardStoreIds()`
- `app/Services/CommissionService.php`

## ポイント

**売上に数える注文を決めておく**
`OrderStatus::settled()` ＝「受取済み」「お礼済み」だけ。リクエスト中・期限切れ・返金は数えません。この決まりを1か所にまとめているので、ダッシュボード・店舗詳細・売上集計・代理店で数え方がそろいます。

**SQL を見る**
売上集計の「店舗別売上」の下の **この表を作っている SQL** を開くと、実際に実行された SQL が見られます（`toRawSql()`）。`GROUP BY` で店舗ごとにまとめています。

```sql
select stores.name, COUNT(*) AS count, SUM(orders.amount) AS sales, SUM(orders.commission) AS commission, ...
from "orders" inner join "stores" on "stores"."id" = "orders"."store_id"
where "orders"."status" in ('received', 'thanked') and ...
group by "stores"."id", "stores"."name"
```

**代理店報酬 ＝ 売上 × 1次代理店の報酬率**
2次代理店経由の店舗の売上も、1次代理店に計上します。

## 確かめる

VS Code の拡張機能「SQLite Viewer」などで `database/database.sqlite` を開き、`orders` テーブルを見ながら、画面の数字と一致するか電卓で確かめる。

## 課題

| レベル | 内容 |
|---|---|
| C5-1 | 売上集計に「決済方法別（カード／振込）」の表を足す（GROUP BY payment_method） |
| C5-2 | 売上集計を CSV でダウンロードできるようにする（`response()->streamDownload()`） |
| C5-3 | 店舗ごとの「月別の振込予定表」（月・売上・手数料・振込額）を作る。手数料は注文ごとに保存した `commission` を使う理由も説明する |
