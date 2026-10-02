# GitHub の設定をコードで管理する（Terraform）

このフォルダは教材アプリ本体とは関係ありません。受講者は触らなくて大丈夫です。

リポジトリの **main ブランチの保護ルール** を Terraform で管理しています。

- main には直接 push できない（必ず PR を通す）
- CI の6つのチェックが全部 ✅ にならないと、PR をマージできない
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
export GITHUB_TOKEN=（repo 権限のあるトークン）   # トークンはファイルに書かない

terraform init     # 初回だけ：GitHub 用の部品をダウンロード
terraform plan     # 「何が変わるか」を見るだけ（GitHub はまだ変わらない）
terraform apply    # 実際に GitHub の設定を変える（yes と打つと実行）
```

## 設定を変えたいとき

1. `.tf` ファイルを書き換える（例：CI にジョブを足したら `variables.tf` の `required_checks` にも足す）
2. `terraform plan` で差分を確認する
3. `terraform apply` で反映する
4. 変更した `.tf` を PR にして main に入れる

GitHub の画面で設定を手で変えると、コードと実物がずれます（ドリフト）。
`terraform plan` を実行すると、そのずれが表示されます。

## 注意

- `terraform.tfstate`（状態ファイル）は、自分の PC にだけ置きます。GitHub には上げません（`.gitignore` 済み）
- CI のジョブ名（`ci.yml` の `name:`）を変えたら、`required_checks` も同じ名前に直します。直さないと、存在しないチェックを待ち続けてマージできなくなります
