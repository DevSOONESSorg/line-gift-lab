# B6 発展：要件変更カード

早く進んだ人向けです。お店（お客さん）から、こんな要望が来たという設定です。1枚ずつ、**要件を整理 → どのファイルを変えるか決める → 実装 → 確認 → 報告** の順で進めてください。AI に相談してかまいません。

| カード | 要望 | ヒント |
|---|---|---|
| 1 | 「リッチメニューの"予約"ボタンを押したら、日付を選べるようにしてほしい」 | postback アクションの `mode: "datetime"`（datetimepicker）。bot 側は `event.postback.params.datetime` |
| 2 | 「返事を文字だけじゃなく、ボタン付きのカードにしてほしい」 | Flex Message。[lineClient.js](../../src/app/lineClient.js) は今テキストしか送れないので、送れるように変える |
| 3 | 「お礼のメッセージを、お店ごとに決めた文にしたい」 | stores テーブルに列を追加 → 管理画面の編集フォーム → liff.js の thanks。`db.js`・`routes/`・`views/` の3か所 |
| 4 | 「贈り物を受け取ったら、買った人に"受け取られました"と通知してほしい」 | liff.js の receive の処理に push を追加 |
| 5 | 「LIFFのユーザーIDをそのまま信じるのは危ないと聞いた」 | 画面で `liff.getIDToken()` → サーバーで `POST https://api.line.me/oauth2/v2.1/verify`（id_token と client_id＝LINEログインチャネルID）で検証してから注文を作る |
| 6 | 「会員（1回以上買った人）だけ、別のリッチメニューにしたい」 | 注文ができたときに、その人に richmenu を link する（B4-3 のしくみを bot に組み込む） |
