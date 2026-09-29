# B9 片付け（必ずやる）

体験で作ったものは、終わったら削除します。画面の文言は変わることがあります。

## 1. API で作ったリッチメニュー（B4）

```bash
docker compose exec app php artisan lab:richmenu delete-all --store=yamada-real
```

## 2. 公式アカウント

1. Manager で自分の公式アカウントを開く
2. **設定 → アカウント設定** を一番下までスクロール →「**アカウントを削除**」
3. 注意事項を読み、同意して削除

## 3. LINE Developers（B3・B5）

1. LINEログインチャネル：チャネルを開き、基本設定の一番下からチャネルを削除
2. 公式アカウントを削除すると、Messaging API チャネルも使えなくなります。コンソールに残っている場合は、同じように削除
3. 中身が空になったら、自分で作ったプロバイダーも削除（プロバイダーの設定から）

## 4. スマホ

- 体験で友だち追加した公式アカウントをブロック・削除する

## 5. 教材アプリ

```bash
docker compose down
docker compose up -d && docker compose exec app php artisan migrate:fresh --seed && docker compose down
```



## 6. 方法B（メールアドレスのビジネスID）の人

ビジネスIDは残しておいても害はありませんが、不要なら <https://account.line.biz/> のアカウント設定から削除できます。
