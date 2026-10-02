# =====================================================
# 使う道具とその版
#   terraform init を実行すると、ここに書いた「GitHub 用の部品（プロバイダー）」が
#   .terraform/ フォルダにダウンロードされます。
# =====================================================
terraform {
  required_version = ">= 1.6"

  required_providers {
    github = {
      source  = "integrations/github" # GitHub を操作するための部品
      version = "~> 6.13"             # 6.13 以上・7.0 未満を使う（大きな変更が入る 7 は避ける）
    }
  }
}

# GitHub への接続設定
#   トークン（合言葉）はここには書かず、環境変数 GITHUB_TOKEN から読み込ませる。
#   → ファイルを GitHub に push しても、トークンは漏れない
provider "github" {
  owner = var.owner
}
