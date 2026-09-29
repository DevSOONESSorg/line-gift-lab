# B4 リッチメニューを API で作る

> Manager のリッチメニューは「全員に同じもの」。API を使うと、ボタンの種類が増え、タブで切り替えたり、人ごとにちがうメニューを出したりできます。

## しくみ

リッチメニューを API で作るときは、次の3段階です。

1. **定義を登録**：「どの四角を押すと何が起きるか」を JSON で送る → `richMenuId` が返ってくる
2. **画像をアップロード**：その `richMenuId` に画像を付ける
3. **表示する**：全員のデフォルトにする／特定の人に紐付ける

教材には、これを実行する artisan コマンド [app/Console/Commands/RichMenu.php](../../app/Console/Commands/RichMenu.php)（`php artisan lab:richmenu`）と、定義の JSON（[richmenus/](../../richmenus/)）が入っています。

## 1. 6分割メニュー（postback つき）

JSON を開いて、中身を読んでみましょう：[richmenus/six.json](../../richmenus/six.json)

- `bounds` … 押せる四角（ピクセルで、x・y・幅・高さ）
- `action.type` … `uri`（リンク）／ `message`（テキスト）／ `postback`（**画面には出さずに、データだけ bot に送る**）

実行します（B3 で作った店舗の slug を `--store` に入れる。トークンは管理画面に入れたものが使われます）。

```bash
docker compose exec app php artisan lab:richmenu create richmenus/six.json richmenus/images/six.png --store=yamada-real
```

`○ 作成しました: richmenu-xxxxxxxx` と出たら、その ID を全員のデフォルトにします。

```bash
docker compose exec app php artisan lab:richmenu default richmenu-xxxxxxxx --store=yamada-real
```

スマホでトークを開き直し、「postbackテスト」を押す → bot が「ボタンが押されました（data: action=test&item=1）」と返せば成功です。

> 「贈る」ボタンは B5 で LIFF を作るまで動きません（`{LIFF_ID}` のままのため）。

## 2. タブで切り替わるメニュー

`richmenuswitch` アクションと **エイリアス**（メニューのあだ名）を使うと、タブのように切り替えられます。

```bash
docker compose exec app php artisan lab:richmenu setup-tabs --store=yamada-real
```

スマホで「タブB：お店の情報」を押すとメニューが切り替わり、「営業時間」を押すと bot が返事をします（[richmenus/tab-b.json](../../richmenus/tab-b.json) の postback `info=hours` を、[StoreBot.php](../../app/Services/Bots/StoreBot.php) が受け取っています）。

## 3. 人ごとにちがうメニュー

自分のユーザーIDは、裏側ビューの Webhook受信 の行を開くと `"userId": "U..."` で見つかります。

```bash
docker compose exec app php artisan lab:richmenu list --store=yamada-real
docker compose exec app php artisan lab:richmenu link Uxxxxxxxx richmenu-yyyyyyyy --store=yamada-real
```

自分にだけ、別のメニューが出ます。会員と非会員でメニューを変える、などに使うしくみです。

## 表示の優先順位

同じ人に複数のリッチメニューが当てはまるときは、次の順で表示されます。

1. その人に紐付けたメニュー（`link`）
2. API で設定したデフォルト（`default`）
3. Manager で作ったメニュー

「Manager で直したのに変わらない」ときは、API のメニューが優先されていないか確認しましょう。片付けは `delete-all` です。

## 課題

| レベル | 要件 |
|---|---|
| B4-1 | six.json の「お店の場所」の地図URLを、自分の好きな場所に変えて作り直す |
| B4-2 | 小・3分割（2500×843）のメニューを、JSON を自分で書いて作る（画像はテンプレートでよい） |
| B4-3 | postback の `data` を見て、bot の返事を分ける（例：`item=1` なら A、`item=2` なら B） |
