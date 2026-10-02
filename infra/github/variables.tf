# =====================================================
# 変数（あとから変えたくなりそうな値は、ここにまとめておく）
# =====================================================

variable "owner" {
  description = "リポジトリを持っている組織（またはユーザー）"
  type        = string
  default     = "DevSOONESSorg"
}

variable "repository" {
  description = "対象のリポジトリ名"
  type        = string
  default     = "line-gift-lab"
}

variable "branch" {
  description = "守るブランチ"
  type        = string
  default     = "main"
}

# マージの条件にする CI のチェック。
# .github/workflows/ci.yml の各ジョブの name: と、1文字も違わずに同じにする。
# 「手順書の外部リンク」は週1回の定期実行でしか動かないので、ここには入れない
# （入れると PR では永遠に結果が出ず、マージできなくなる）
variable "required_checks" {
  description = "緑（✅）にならないとマージできないチェックの名前"
  type        = list(string)
  default = [
    "起動とテスト",
    "コードの品質",
    "全画面の巡回",
    "ブラウザ自動操作",
    "Docker で起動",
    "手順書のリンク",
  ]
}
