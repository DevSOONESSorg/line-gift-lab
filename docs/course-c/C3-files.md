# C3 ファイルと署名付きURL

## 読むファイル

- `app/Http/Controllers/Liff/OwnerController.php` の `thank()` … 動画のアップロード
- `app/Http/Controllers/Liff/HistoryController.php` の `thanks()` と `video()`
- `routes/web.php` の `media/thank-videos/{order}` … `->middleware('signed')`
- `config/filesystems.php` … `local`（private）と `public` の2つの保存場所

## ポイント

**1. 公開する場所・しない場所**

| 保存場所 | パス | 外から見える？ | 教材で入れているもの |
|---|---|---|---|
| public ディスク | `storage/app/public`（`public/storage` からリンク） | 見える（URLを知っていれば誰でも） | リッチメニューの画像 |
| local（private） | `storage/app/private` | 見えない | お礼動画・身分証の画像 |

お礼動画や身分証は、関係のない人に見られてはいけないので「見えない場所」に置きます。

**2. 見せるときは「署名付きURL」**
`URL::temporarySignedRoute('media.thank-video', now()->addMinutes(10), $order)` は、URLの最後に「期限」と「署名」が付いたURLを作ります。ルートの `signed` ミドルウェアが署名を確かめ、書きかえられたURLや期限切れのURLは **403** になります。

> 本番の管理画面を調べたとき、お礼動画のURLに署名や期限が付いていないように見えました。URLが漏れると誰でもずっと見られる可能性があるので、運営に確認をお願いしています。こういう「気づき」を持てるのが、仕組みを理解したエンジニアです。

**3. アップロードは必ず検証する**
`'video' => 'nullable|file|mimetypes:video/mp4,video/quicktime,video/webm|max:20480'`：種類と大きさを確かめています。拡張子だけでなく中身で判定します。

## 確かめる

1. 管理画面の注文詳細でお礼動画を開き、ブラウザのアドレス欄に動画のURLを出す（右クリック → 動画のアドレスをコピー）
2. URL の `signature=` の1文字を変えて開く → 403
3. 10分待ってから同じURLを開く → 403

## 課題

| レベル | 内容 |
|---|---|
| C3-1 | 署名付きURLの有効時間を 10分 → 30分 にする（2か所あります。1か所にまとめる方法も考える） |
| C3-2 | お店の「店舗画像」をアップロードできるようにする（管理画面の編集）。public ディスクに保存し、店舗詳細と「お店をさがす」に表示。画像以外・5MB超はエラー |
| C3-3 | 身分証の画像を、管理画面の「ユーザー詳細」から **運営だけ** が見られるようにする（署名付きURL＋管理画面のログイン必須） |
