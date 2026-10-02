# GitHub の設定をコードで管理する（Terraform）

このフォルダは教材アプリ本体とは関係ありません。受講者は触らなくて大丈夫です。

リポジトリの **main ブランチの保護ルール** を Terraform で管理しています。

- main には直接 push できない（必ず PR を通す）
- CI の必須チェック（`variables.tf` の `required_checks`）が全部 ✅ にならないと、PR をマージできない
- 管理者も例外にしない

| ファイル | 中身 |
|---|---|
| `versions.tf` | 使う Terraform と GitHub 用の部品（プロバイダー）の版 |
| `variables.tf` | 組織名・リポジトリ名・守るブランチ・必須のチェック名 |
| `main.tf` | 保護ルールの中身 |
| `outputs.tf` | 実行後に表示する確認用の情報 |

## 使い方

```bash
cd infra/github
export GITHUB_TOKEN='ghp_...'   # repo と read:org 権限のある、期限付きトークン。ファイルには書かない

terraform init     # 初回だけ：GitHub 用の部品をダウンロード
terraform plan     # 「何が変わるか」を見るだけ（GitHub はまだ変わらない）
terraform apply    # 実際に GitHub の設定を変える（yes と打つと実行）
```

## 設定を変えるときの流れ（PR → マージ → apply）

コードの変更とまったく同じく、**まず PR で中身を確かめ、main に入ってから本番（GitHub の設定）に反映**します。

1. ブランチを作り、`.tf` ファイルを書き換える（例：CI にジョブを足したら `variables.tf` の `required_checks` にも足す）
2. 手元で `terraform fmt -recursive` と `terraform plan` を実行し、差分が想定どおりか確かめる
3. PR を出す。CI の「Terraform のチェック」が、書き方（fmt）・文法（validate）・よくあるミス（TFLint）を確かめる
4. PR に `terraform plan` の結果を貼っておくと、あとから「何を変えたか」が追える
5. マージしたら、main を取り込んでから `terraform apply` で反映し、もう一度 `terraform plan` で `No changes.` を確かめる

GitHub の画面で設定を手で変えると、コードと実物がずれます（ドリフト）。
`terraform plan` を実行すると、そのずれが表示されます。

## 注意

- `terraform.tfstate`（状態ファイル）は、`terraform apply` を実行した PC にだけあります。GitHub には上げません（`.gitignore` 済み）。消さないこと
- CI のジョブ名（`ci.yml` の `name:`）を変えたら、`required_checks` も同じ名前に直します。直さないと、存在しないチェックを待ち続けてマージできなくなります
- 必須チェックを**増やす**ときは、そのジョブが入った ci.yml を先に main へマージしてから apply します。順番が逆だと、ほかの PR がまだ存在しないチェックを待ち続けて止まります

## この先（チームで使うようになったら）

いまは「状態ファイルが1台の PC にある」「apply は人が手元で行う」形です。複数人で触るようになったら、次が定番です。

- 状態ファイルを共有の置き場所（HCP Terraform、S3 など）に移す
- PR を出すと CI が `terraform plan` を実行して結果を PR にコメントし、マージすると CI が `terraform apply` する
